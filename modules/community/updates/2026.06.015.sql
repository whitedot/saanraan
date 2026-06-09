ALTER TABLE sr_community_comments
    ADD COLUMN parent_comment_id BIGINT UNSIGNED NULL AFTER post_id,
    ADD COLUMN thread_root_id BIGINT UNSIGNED NULL AFTER parent_comment_id,
    ADD COLUMN depth TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER thread_root_id,
    ADD KEY idx_sr_community_comments_thread (post_id, status, thread_root_id, parent_comment_id, id),
    ADD KEY idx_sr_community_comments_parent (parent_comment_id);

UPDATE sr_community_comments
SET thread_root_id = id,
    depth = 1
WHERE thread_root_id IS NULL;
