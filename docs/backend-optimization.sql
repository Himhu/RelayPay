-- Optional indexes to speed up admin lists and stats.
-- Review in your DB and apply as needed.

-- User list: latest login and filters
CREATE INDEX idx_admin_front_log_uid_type_id ON admin_front_log (uid, type, id);
CREATE INDEX idx_admin_front_log_ip_time ON admin_front_log (ip, create_time);

-- Orders: user/account filters + time ranges
CREATE INDEX idx_ypay_order_user_status_time ON ypay_order (user_id, status, create_time);
CREATE INDEX idx_ypay_order_account_status_time ON ypay_order (account_id, status, create_time);
CREATE INDEX idx_ypay_order_trade_no ON ypay_order (trade_no);
CREATE INDEX idx_ypay_order_out_trade_no ON ypay_order (out_trade_no);

-- Recharges: user + time
CREATE INDEX idx_ypay_recharge_user_time ON ypay_recharge (user_id, create_time);
CREATE INDEX idx_ypay_recharge_status_time ON ypay_recharge (status, create_time);

-- Money log: user + time
CREATE INDEX idx_money_log_user_time ON money_log (user_id, create_time);
CREATE INDEX idx_money_log_memo_time ON money_log (memo, create_time);
