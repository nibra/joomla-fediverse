-- ---------------------------------------------------------------------------
-- pkg_fediverse / com_fediverse
-- Uninstall schema (MySQL)
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS `#__fediverse_domain_policies`;
DROP TABLE IF EXISTS `#__fediverse_content_settings`;
DROP TABLE IF EXISTS `#__fediverse_user_settings`;
DROP TABLE IF EXISTS `#__fediverse_delivery_queue`;
DROP TABLE IF EXISTS `#__fediverse_oauth_tokens`;
DROP TABLE IF EXISTS `#__fediverse_oauth_authcodes`;
DROP TABLE IF EXISTS `#__fediverse_oauth_clients`;
DROP TABLE IF EXISTS `#__fediverse_inbox`;
DROP TABLE IF EXISTS `#__fediverse_inbound_activities`;
DROP TABLE IF EXISTS `#__fediverse_outbox`;
DROP TABLE IF EXISTS `#__fediverse_media`;
DROP TABLE IF EXISTS `#__fediverse_followers`;
DROP TABLE IF EXISTS `#__fediverse_keys`;
DROP TABLE IF EXISTS `#__fediverse_actors`;
