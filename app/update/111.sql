UPDATE `ypay_userbasic`
SET `appkey` = SUBSTRING(CONCAT(
    SUBSTRING(MD5(RAND()), 1, 16), 
    SUBSTRING(MD5(RAND()), 1, 16)
), 1, 32);

INSERT INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES (NULL, '34', '支付类型', '/ypay.payment/index', 'layui-icon layui-icon layui-icon-fire', '96', '1', '1'), (NULL, '167', '新增支付类型', '/ypay.payment/add', NULL, '99', '1', '1'), (NULL, '167', '修改支付类型', '/ypay.payment/edit', NULL, '99', '1', '1'), (NULL, '167', '删除支付类型', '/ypay.payment/remove', NULL, '99', '1', '1'), (NULL, '167', '批量删除支付类型', '/ypay.payment/batchRemove', NULL, '99', '1', '1'), (NULL, '167', '回收站支付类型', '/ypay.payment/recycle', NULL, '99', '1', '1');

--
-- 表的结构 `ypay_payment`
--

CREATE TABLE `ypay_payment` (
  `id` int(11) UNSIGNED NOT NULL COMMENT 'id',
  `name` varchar(255) DEFAULT NULL COMMENT '支付名称',
  `type` varchar(255) DEFAULT NULL COMMENT '支付类型',
  `sort` varchar(255) DEFAULT NULL COMMENT '排序',
  `status` varchar(255) DEFAULT NULL COMMENT '状态',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  `delete_time` timestamp NULL DEFAULT NULL COMMENT '删除时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付类型';

--
-- 转存表中的数据 `ypay_payment`
--

INSERT INTO `ypay_payment` (`id`, `name`, `type`, `sort`, `status`, `create_time`, `update_time`, `delete_time`) VALUES
(1, '微信', 'wxpay', '50', '1', '2025-01-18 14:16:07', '2025-01-18 15:15:42', NULL),
(2, '支付宝', 'alipay', '50', '1', '2025-01-18 14:16:37', '2025-01-18 15:15:42', NULL),
(3, 'QQ', 'qqpay', '50', '1', '2025-01-18 14:16:49', '2025-01-18 15:15:41', NULL);

--
-- 转储表的索引
--

--
-- 表的索引 `ypay_payment`
--
ALTER TABLE `ypay_payment`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `ypay_payment`
--
ALTER TABLE `ypay_payment`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id', AUTO_INCREMENT=4;
COMMIT;