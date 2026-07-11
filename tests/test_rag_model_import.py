from app.rag.models import ArticleChunk, EMBEDDING_DIM


def test_rag_models_import_and_vector_dimensions():
    assert ArticleChunk.__tablename__ == "article_chunks"
    assert EMBEDDING_DIM == 384
