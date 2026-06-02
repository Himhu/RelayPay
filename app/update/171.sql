UPDATE `admin_permission` SET `sort` = '5' WHERE `title` = '后台权限';
-- 插入主题设置记录，并获取插入记录的 id
INSERT INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) 
VALUES (NULL, '0', '主题设置', '', 'layui-icon layui-icon-layouts', '4', '0', '1');

-- 获取刚刚插入的主题设置记录的 id
SET @theme_setting_id = LAST_INSERT_ID();

-- 更新 title 为 会员中心主题 的记录的 title 为会员中心模板
UPDATE `admin_permission` 
SET `title` = '会员中心模板' 
WHERE `title` = '会员中心主题' ;

-- 更新 title 为 会员中心模板 的记录的 pid 为主题设置的 id
UPDATE `admin_permission` 
SET `pid` = @theme_setting_id 
WHERE `title` = '会员中心模板';

-- 更新 title 为 首页模板 的记录的 pid 为主题设置的 id
UPDATE `admin_permission` 
SET `pid` = @theme_setting_id 
WHERE `title` = '首页模板';

-- 插入下单界面模板记录，其 pid 为主题设置的 id
INSERT INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) 
VALUES (NULL, @theme_setting_id, '下单界面模板', '/ypay.pay_theme/index', 'layui-icon layui-icon-face-smile', '99', '1', '1');