-- cleanup_whatsapp.sql
-- Safe cleanup script for WhatsApp/system client accounts
-- IMPORTANT: Take a DB backup before running. This script defaults to DRY RUN.
-- Usage: open the file, review results, then set p_execute := true to actually delete.

-- Example backup command (run outside psql):
-- pg_dump -U <dbuser> -h <dbhost> -d <dbname> -Fc -f backup_before_cleanup.dump

DO $$
DECLARE
  p_execute BOOLEAN := false; -- set to true to perform deletions
  r RECORD;
  c_messages INT;
  c_convs INT;
  c_tickets INT;
  c_gmail INT;
BEGIN
  RAISE NOTICE 'Scanning for WhatsApp/system users (role = client)...';

  FOR r IN
    SELECT id, name, email FROM users
    WHERE role = 'client'
      AND (
        email ILIKE '%@whatsapp.local' OR
        email LIKE 'wa_%' OR
        email ILIKE '%noreply%' OR
        email ILIKE '%no-reply%' OR
        name ILIKE '%whatsapp%'
      )
  LOOP
    -- gather counts (cast to text for cross-type safety)
    SELECT COUNT(*) INTO c_messages FROM messages WHERE cast(sender_id AS text) = cast(r.id AS text);
    SELECT COUNT(*) INTO c_convs    FROM conversations WHERE cast(user_id AS text) = cast(r.id AS text);
    SELECT COUNT(*) INTO c_tickets  FROM tickets WHERE cast(user_id AS text) = cast(r.id AS text);
    SELECT COUNT(*) INTO c_gmail    FROM gmail_credentials WHERE cast(user_id AS text) = cast(r.id AS text);

    IF NOT p_execute THEN
      RAISE NOTICE '[DRY RUN] User: id=% name=% email=% | messages=% convs=% tickets=% gmail=%', r.id, r.name, r.email, c_messages, c_convs, c_tickets, c_gmail;
    ELSE
      BEGIN
        -- Per-user transaction block: attempt deletes and continue on error
        -- 1) delete messages referencing this user as sender
        DELETE FROM messages WHERE cast(sender_id AS text) = cast(r.id AS text);

        -- 2) delete conversation_agent_reply_suspensions referencing conversations of this user (if table exists)
        PERFORM 1 FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = 'conversation_agent_reply_suspensions';
        IF FOUND THEN
          DELETE FROM conversation_agent_reply_suspensions WHERE conversation_id IN (SELECT id FROM conversations WHERE cast(user_id AS text) = cast(r.id AS text));
        END IF;

        -- 3) delete conversation_snippets entries referencing conversation_id if any orphan rows exist (safe guard)
        -- (most snippet tables refer to conversation via conversation_id only in some schemas; adjust as needed)

        -- 4) delete conversations for this user
        DELETE FROM conversations WHERE cast(user_id AS text) = cast(r.id AS text);

        -- 5) delete tickets
        DELETE FROM tickets WHERE cast(user_id AS text) = cast(r.id AS text);

        -- 6) delete gmail credentials
        DELETE FROM gmail_credentials WHERE cast(user_id AS text) = cast(r.id AS text);

        -- 7) finally delete the user
        DELETE FROM users WHERE id = r.id;

        RAISE NOTICE '[DELETED] User id=% email=% (messages=% convs=% tickets=% gmail=%)', r.id, r.email, c_messages, c_convs, c_tickets, c_gmail;
      EXCEPTION WHEN OTHERS THEN
        RAISE WARNING 'Failed to remove user id=% email=% ; error: %', r.id, r.email, SQLERRM;
        -- continue loop
      END;
    END IF;
  END LOOP;

  IF NOT p_execute THEN
    RAISE NOTICE 'Dry run complete. To execute deletions, edit this file and set p_execute := true; then run it again.';
  ELSE
    RAISE NOTICE 'Execution complete.';
  END IF;
END$$;

-- End of script
