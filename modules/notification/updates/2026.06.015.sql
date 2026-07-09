UPDATE sr_notification_event_templates
SET body_template = CONCAT(body_template, '\n\n{link_url}'),
    updated_at = NOW()
WHERE link_template = '{link_url}'
  AND (
    (module_key = 'content' AND event_key = 'comment.created' AND body_template = '{member_name}님이 회원님의 콘텐츠에 댓글을 남겼습니다.')
    OR (module_key = 'content' AND event_key = 'comment.mention' AND body_template = '{member_name}님이 콘텐츠 댓글에서 회원님을 언급했습니다.')
    OR (module_key = 'content' AND event_key = 'followed_author.content_created' AND body_template = '콘텐츠: {content_title}\n등록 시각: {created_at}')
    OR (module_key = 'community' AND event_key = 'comment.created' AND body_template = '{member_name}님이 회원님의 게시글에 댓글을 남겼습니다.')
    OR (module_key = 'community' AND event_key = 'comment.mention' AND body_template = '{member_name}님이 커뮤니티 댓글에서 회원님을 언급했습니다.')
    OR (module_key = 'community' AND event_key = 'followed_author.post_created' AND body_template = '게시판: {board_title}\n게시글: {post_title}\n등록 시각: {created_at}')
    OR (module_key = 'quiz' AND event_key = 'comment.mention' AND body_template = '{member_name}님이 퀴즈 댓글에서 회원님을 언급했습니다.')
    OR (module_key = 'survey' AND event_key = 'comment.mention' AND body_template = '{member_name}님이 설문 댓글에서 회원님을 언급했습니다.')
    OR (module_key = 'reaction' AND event_key = 'target.reacted' AND body_template = '{member_name}님이 {target_label}에 {reaction_label} 리액션을 남겼습니다.')
    OR (module_key = 'community' AND event_key = 'attachment.publisher_reward.granted' AND body_template = '지급 금액: {amount}{asset}')
  );
