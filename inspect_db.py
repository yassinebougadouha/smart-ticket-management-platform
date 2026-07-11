import asyncio
from app.db.session import async_session_factory
from app.db.models.user import User
from app.db.models.conversation import Conversation, Message
from sqlalchemy import select

async def main():
    async with async_session_factory() as session:
        # Get users
        res_users = await session.execute(select(User))
        users = res_users.scalars().all()
        print("--- USERS ---")
        for u in users:
            print(f"ID: {u.id} | Email: {u.email} | Role: {u.role} | Laravel User ID: {u.laravel_user_id}")

        # Get conversations
        res_convs = await session.execute(select(Conversation))
        convs = res_convs.scalars().all()
        print("\n--- CONVERSATIONS ---")
        for c in convs:
            print(f"ID: {c.id} | User ID: {c.user_id} | Channel: {c.channel} | Status: {c.status} | Subject: {c.subject}")

        # Get messages count
        res_msgs = await session.execute(select(Message))
        msgs = res_msgs.scalars().all()
        print(f"\nTotal messages: {len(msgs)}")
        for m in msgs[:20]:
            print(f"Msg ID: {m.id} | Conv ID: {m.conversation_id} | Sender ID: {m.sender_id} | Content: {m.content[:40]}")

if __name__ == '__main__':
    asyncio.run(main())
