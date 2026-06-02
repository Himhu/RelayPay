ALTER TABLE `ypay_cloud` ADD `cloud_proxy` VARCHAR(50) NOT NULL COMMENT '代理开关' AFTER `cloud_type`;
ALTER TABLE `ypay_cloud` ADD `proxy_address` VARCHAR(255) NOT NULL COMMENT '代理地址' AFTER `cloud_proxy`;
ALTER TABLE `ypay_cloud` ADD `proxy_account` VARCHAR(255) NOT NULL COMMENT '代理账号' AFTER `proxy_address`;
ALTER TABLE `ypay_cloud` ADD `proxy_password` VARCHAR(255) NOT NULL COMMENT '代理账号' AFTER `proxy_account`;
INSERT INTO `admin_channel` (`id`, `name`, `type`, `create_type`, `code`, `info`, `status`, `create_time`, `sort`, `maxcount`) VALUES (NULL, '微信经营码 - 云端免输入', 'wxpay', '1', 'wxpay_jym_cloud', '微信经营码 - 云端免输入', '1', '2025-01-10 23:40:51', '0', '10');