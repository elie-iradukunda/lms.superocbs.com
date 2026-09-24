-- A photo that belongs to the person it is shown next to.
--
-- Every signed-in user was shown the same stock photograph of a stranger,
-- because the theme shipped with one and nothing replaced it. It is a small
-- thing and it undermines the whole screen: a system that cannot get your own
-- face right is not obviously getting anything else right either.
--
-- The column holds a path relative to the uploads folder, the same way proof of
-- delivery and receipts already do. Nobody is obliged to add one — an account
-- with no photo is shown its own initials, which is at least honest.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL AFTER job_title;
