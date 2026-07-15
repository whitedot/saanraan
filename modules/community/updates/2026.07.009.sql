UPDATE {{SR_TABLE_PREFIX}}community_comments c
INNER JOIN {{SR_TABLE_PREFIX}}community_comments parent
    ON parent.id = c.parent_comment_id
   AND parent.post_id = c.post_id
SET c.thread_root_id = COALESCE(parent.thread_root_id, parent.id),
    c.depth = LEAST(3, GREATEST(2, parent.depth + 1))
WHERE c.thread_root_id IS NULL;

UPDATE {{SR_TABLE_PREFIX}}community_comments
SET thread_root_id = id,
    depth = 1
WHERE thread_root_id IS NULL;

ALTER TABLE {{SR_TABLE_PREFIX}}community_comments
    DROP INDEX idx_sr_community_comments_thread,
    ADD KEY idx_sr_community_comments_thread (post_id, status, thread_root_id, depth, id);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.009',
    updated_at = NOW()
WHERE module_key = 'community';
