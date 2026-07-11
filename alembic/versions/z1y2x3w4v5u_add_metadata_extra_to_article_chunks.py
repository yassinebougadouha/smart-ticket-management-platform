"""add metadata_extra to article_chunks

Revision ID: z1y2x3w4v5u
Revises: e5f6a7b8c9d0
Create Date: 2026-06-25
"""

from alembic import op
import sqlalchemy as sa

revision = "z1y2x3w4v5u"
down_revision = "e5f6a7b8c9d0"
branch_labels = None
depends_on = None


def upgrade() -> None:
    bind = op.get_bind()
    inspector = sa.inspect(bind)
    existing_columns = {column["name"] for column in inspector.get_columns("article_chunks")}

    if "metadata_extra" not in existing_columns:
        op.add_column(
            "article_chunks",
            sa.Column("metadata_extra", sa.JSON(), nullable=True),
        )


def downgrade() -> None:
    bind = op.get_bind()
    inspector = sa.inspect(bind)
    existing_columns = {column["name"] for column in inspector.get_columns("article_chunks")}

    if "metadata_extra" in existing_columns:
        op.drop_column("article_chunks", "metadata_extra")
