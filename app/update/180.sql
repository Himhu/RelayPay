INSERT INTO `admin_config` (`id`, `config_name`, `config_value`) VALUES (NULL, 'demo_theme', 'default');
INSERT INTO `admin_permission` (`pid`, `title`, `href`, `icon`, `sort`, `type`, `status`)
SELECT ap.id, '测试界面模板', '/ypay.demo_theme/index', 'layui-icon layui-icon-face-smile', '99', '1', '1'
FROM `admin_permission` ap
WHERE ap.title = '主题设置';
INSERT INTO `admin_config` (`id`, `config_name`, `config_value`) VALUES (NULL, 'doc_theme', 'default');
INSERT INTO `admin_permission` (`pid`, `title`, `href`, `icon`, `sort`, `type`, `status`)
SELECT ap.id, '文档界面模板', '/ypay.doc_theme/index', 'layui-icon layui-icon-face-smile', '99', '1', '1'
FROM `admin_permission` ap
WHERE ap.title = '主题设置';
INSERT INTO `admin_config` (`id`, `config_name`, `config_value`) VALUES (NULL, 'news_theme', 'default');
INSERT INTO `admin_permission` (`pid`, `title`, `href`, `icon`, `sort`, `type`, `status`)
SELECT ap.id, '公告界面模板', '/ypay.news_theme/index', 'layui-icon layui-icon-face-smile', '99', '1', '1'
FROM `admin_permission` ap
WHERE ap.title = '主题设置';
ALTER TABLE `ypay_account` ADD `qr_type` VARCHAR(50) NOT NULL COMMENT '收款类型' AFTER `qr_url`;