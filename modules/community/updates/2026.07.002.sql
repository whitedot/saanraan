ALTER TABLE sr_community_posts
    ADD COLUMN is_notice TINYINT(1) NOT NULL DEFAULT 0 AFTER is_secret,
    ADD KEY idx_sr_community_posts_board_notice_status_id (board_id, is_notice, status, id);
