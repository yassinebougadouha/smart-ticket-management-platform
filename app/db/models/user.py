"""
User model — supports CLIENT, AGENT, ADMIN roles.

Laravel compatibility:
  The shared `users` table was originally created by Laravel migrations.
  It has NOT-NULL columns `name` and `password` that Laravel owns.
  FastAPI uses `full_name` and `hashed_password` instead.
  The `before_insert` / `before_update` event listeners below ensure
  the Laravel columns are always kept in sync before any DB write.
"""

from sqlalchemy import Boolean, String, Enum, Index, BigInteger, event
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db.base import Base, TimestampMixin, SoftDeleteMixin
from app.db.models.enums import UserRole, UserStatus


class User(Base, TimestampMixin, SoftDeleteMixin):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)

    # ── FastAPI-owned columns ──────────────────────
    email: Mapped[str] = mapped_column(String(255), unique=True, nullable=False, index=True)
    hashed_password: Mapped[str] = mapped_column(String(255), nullable=False)
    full_name: Mapped[str] = mapped_column(String(255), nullable=False)

    # ── Laravel compatibility columns (kept in sync via event listeners) ──
    name: Mapped[str] = mapped_column(String(255), nullable=False, default="")
    password: Mapped[str] = mapped_column(String(255), nullable=False, default="")
    laravel_role: Mapped[str] = mapped_column(
        "role", String(255), nullable=False, default="client"
    )

    # ── Other columns ──────────────────────────────
    phone_number: Mapped[str | None] = mapped_column(
        String(20), unique=True, nullable=True, index=True,
    )
    role: Mapped[UserRole] = mapped_column(
        "role_python",
        Enum(UserRole, name="user_role", native_enum=False, create_constraint=False),
        default=UserRole.CLIENT,
        nullable=True,
        index=True,
    )
    status: Mapped[UserStatus] = mapped_column(
        Enum(
            UserStatus,
            name="user_status",
            native_enum=False,
            create_constraint=False,
            values_callable=lambda obj: [e.value for e in obj],
        ),
        default=UserStatus.ACTIVE,
        nullable=False,
    )
    can_reply_conversations: Mapped[bool] = mapped_column(default=True, nullable=False)
    can_reply_whatsapp: Mapped[bool] = mapped_column(default=True, nullable=False)
    is_vip: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False, index=True)
    teams_email: Mapped[str | None] = mapped_column(String(255), nullable=True, index=True)
    teams_webhook_url: Mapped[str | None] = mapped_column(String(1000), nullable=True)
    timezone: Mapped[str] = mapped_column(String(64), default="UTC", nullable=False)
    locale: Mapped[str] = mapped_column(String(16), default="en", nullable=False)
    must_change_password: Mapped[bool] = mapped_column(default=False, nullable=False)
    profile_completed: Mapped[bool] = mapped_column(default=False, nullable=False)
    profile_picture_url: Mapped[str | None] = mapped_column(String(1000), nullable=True)
    glpi_user_id: Mapped[int | None] = mapped_column(nullable=True, index=True)
    laravel_user_id: Mapped[int | None] = mapped_column(
        BigInteger, nullable=True, unique=True, index=True,
    )

    # ── Relationships ──────────────────────────────
    conversations = relationship("Conversation", back_populates="user", lazy="select")
    messages = relationship("Message", back_populates="sender", lazy="select")
    assigned_tickets = relationship(
        "Ticket",
        back_populates="assigned_agent",
        foreign_keys="Ticket.assigned_agent_id",
        lazy="select",
    )
    created_tickets = relationship(
        "Ticket",
        back_populates="creator",
        foreign_keys="Ticket.creator_id",
        lazy="select",
    )
    solved_tickets = relationship(
        "Ticket",
        back_populates="solved_by",
        foreign_keys="Ticket.solved_by_id",
        lazy="select",
    )
    notifications = relationship("Notification", back_populates="user", lazy="select")

    __table_args__ = (
        Index("ix_users_email_active", "email", "is_deleted"),
    )

    def __repr__(self) -> str:
        role_val = self.role.value if self.role else "None"
        return f"<User {self.email} role={role_val}>"


# ── Event listeners: sync Laravel compat fields before every DB write ─────────

@event.listens_for(User, "before_insert")
@event.listens_for(User, "before_update")
def _sync_laravel_compat_fields(mapper, connection, target: "User") -> None:
    """Keep `name`, `password`, and `laravel_role` in sync before every flush."""

    # name  <-->  full_name
    if not target.name and target.full_name:
        target.name = target.full_name
    elif target.full_name and not target.name:
        target.name = target.full_name

    # password  <-->  hashed_password
    if not target.password and target.hashed_password:
        target.password = target.hashed_password
    elif target.hashed_password and not target.password:
        target.password = target.hashed_password

    # laravel_role  <--  role  (if not already set)
    if not target.laravel_role:
        if target.role:
            if isinstance(target.role, UserRole):
                target.laravel_role = target.role.value.lower()
            else:
                target.laravel_role = str(target.role).lower()
        else:
            target.laravel_role = "client"