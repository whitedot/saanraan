UPDATE {{SR_TABLE_PREFIX}}quiz_sets
SET quiz_mode = 'scored',
    scoring_model = CASE WHEN scoring_model = '' THEN 'correct_answer' ELSE scoring_model END,
    updated_at = NOW()
WHERE quiz_mode = 'survey';
