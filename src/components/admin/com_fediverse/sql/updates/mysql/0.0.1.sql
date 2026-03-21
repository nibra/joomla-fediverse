ALTER TABLE `#__fediverse_actors`
  ADD COLUMN IF NOT EXISTS `actor_type` ENUM('Person','Service') NOT NULL DEFAULT 'Person' AFTER `is_enabled`,
  ADD COLUMN IF NOT EXISTS `object_type` ENUM('Note','Article','Image','Video') NOT NULL DEFAULT 'Note' AFTER `actor_type`;
