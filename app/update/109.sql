ALTER TABLE `ypay_account` DROP `vcloudurl`;
ALTER TABLE `ypay_account` ADD `cloud_id` VARCHAR(50) NOT NULL COMMENT '云端ID' AFTER `wx_guid`;
CREATE TABLE `ypay_ticket_category` (
  `id` int(11) UNSIGNED NOT NULL COMMENT 'id',
  `name` varchar(255) DEFAULT NULL COMMENT '分类名称',
  `sort` varchar(255) DEFAULT NULL COMMENT '排序',
  `status` varchar(255) DEFAULT NULL COMMENT '状态',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='工单分类';

--
-- 转存表中的数据 `ypay_ticket_category`
--

INSERT INTO `ypay_ticket_category` (`id`, `name`, `sort`, `status`, `create_time`, `update_time`) VALUES
(1, '会员问题', '1', '1', '2025-01-12 17:56:03', '2025-01-12 18:39:51'),
(2, '网站BUG', '2', '1', '2025-01-12 17:57:35', '2025-01-12 17:57:35'),
(3, '其他问题', '3', '1', '2025-01-12 18:04:26', '2025-01-12 18:04:26');

--
-- 转储表的索引
--

--
-- 表的索引 `ypay_ticket_category`
--
ALTER TABLE `ypay_ticket_category`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `ypay_ticket_category`
--
ALTER TABLE `ypay_ticket_category`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id', AUTO_INCREMENT=4;
COMMIT;
INSERT INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES (148, '0', '工单管理', '', 'layui-icon layui-icon-about', '10', '0', '1');
INSERT INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES (NULL, '148', '工单分类', '/ypay.ticket_category/index', 'layui-icon layui-icon layui-icon-fire', '98', '1', '1'), (NULL, '161', '新增工单分类', '/ypay.ticket_category/add', NULL, '99', '1', '1'), (NULL, '161', '修改工单分类', '/ypay.ticket_category/edit', NULL, '99', '1', '1'), (NULL, '161', '删除工单分类', '/ypay.ticket_category/remove', NULL, '99', '1', '1'), (NULL, '161', '批量删除工单分类', '/ypay.ticket_category/batchRemove', NULL, '99', '1', '1'), (NULL, '161', '回收站工单分类', '/ypay.ticket_category/recycle', NULL, '99', '1', '1');
