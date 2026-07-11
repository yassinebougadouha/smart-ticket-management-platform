"""
Knowledge base models.
"""
from __future__ import annotations
import uuid

from sqlalchemy import Boolean, String, Integer, Text, JSON, Index, BigInteger, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship
from pgvector.sqlalchemy import Vector

from app.db.base import Base, TimestampMixin, UUIDPrimaryKeyMixin, SoftDeleteMixin
from app.rag.enums import ArticleStatus, ArticleCategory, ChunkStatus

EMBEDDING_DIM = 384


class KnowledgeArticle(Base, UUIDPrimaryKeyMixin, TimestampMixin, SoftDeleteMixin):
    __tablename__ = "knowledge_articles"

    title: Mapped[str] = mapped_column(String(500), nullable=False, index=True)
    content: Mapped[str] = mapped_column(Text, nullable=False)
    summary: Mapped[str | None] = mapped_column(Text, nullable=True)
    # The shared Laravel database stores these as varchar columns, so we keep
    # them as plain strings here to avoid PostgreSQL enum mismatches.
    category: Mapped[str] = mapped_column(String(100), nullable=False)
    status: Mapped[str] = mapped_column(
        String(50),
        default=ArticleStatus.DRAFT.value,
        nullable=False,
    )
    tags: Mapped[list | None] = mapped_column(JSON, nullable=True)
    source: Mapped[str | None] = mapped_column(String(255), nullable=True)
    language: Mapped[str] = mapped_column(String(10), default="en", nullable=False)
    metadata_extra: Mapped[dict | None] = mapped_column(JSON, nullable=True)
    created_by: Mapped[int | None] = mapped_column(BigInteger, ForeignKey("users.id"), nullable=True)
    updated_by: Mapped[int | None] = mapped_column(BigInteger, ForeignKey("users.id"), nullable=True)
    is_indexed: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    chunk_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    total_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

    chunks = relationship("ArticleChunk", back_populates="article", cascade="all, delete-orphan")
    author = relationship("User", foreign_keys=[created_by])

    __table_args__ = (
        Index("ix_articles_category_status", "category", "status"),
        Index("ix_knowledge_articles_category", "category"),
        Index("ix_knowledge_articles_status", "status"),
        Index("ix_knowledge_articles_title", "title"),
        Index("ix_articles_language", "language"),
    )


class ArticleChunk(Base, UUIDPrimaryKeyMixin, TimestampMixin):
    __tablename__ = "article_chunks"

    article_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("knowledge_articles.id", ondelete="CASCADE"),
        nullable=False, index=True,
    )
    chunk_index: Mapped[int] = mapped_column(Integer, nullable=False)
    content: Mapped[str] = mapped_column(Text, nullable=False)
    token_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    embedding: Mapped[list | None] = mapped_column(Vector(EMBEDDING_DIM), nullable=True)
    status: Mapped[str] = mapped_column(
        String(50),
        default=ChunkStatus.PENDING.value,
        nullable=False,
    )
    metadata_extra: Mapped[dict | None] = mapped_column(JSON, nullable=True)

    article = relationship("KnowledgeArticle", back_populates="chunks")
