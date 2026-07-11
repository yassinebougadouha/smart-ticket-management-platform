import asyncio
import uuid
from app.db.session import async_session_factory
from app.db.models.conversation import Message
from sqlalchemy import select

async def main():
    async with async_session_factory() as session:
        conv_id = uuid.UUID("fd6368a4-1666-43cf-8fe4-0a44dcfa7349")
        res_msgs = await session.execute(
            select(Message).where(Message.conversation_id == conv_id).order_by(Message.created_at)
        )
        msgs = res_msgs.scalars().all()
        print(f"--- MESSAGES FOR {conv_id} ---")
        for m in msgs:
            print(f"ID: {m.id} | Sender ID: {m.sender_id} | Internal: {m.is_internal} | Content: {m.content[:60]}")

if __name__ == '__main__':
    asyncio.run(main())
