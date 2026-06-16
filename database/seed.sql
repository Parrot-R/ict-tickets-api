-- Demo seed data for IT OpsDesk
-- Run after schema.sql (or use backend/seed.php)

USE `tasks_schema`;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `activity`;
TRUNCATE TABLE `assets`;
TRUNCATE TABLE `incidents`;
TRUNCATE TABLE `tasks`;
TRUNCATE TABLE `organizations`;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `organizations` (`id`, `name`, `short_name`, `color`, `contact`, `site_count`) VALUES
('org-acme',     'Acme Manufacturing',  'ACM', '#3b82f6', 'it@acme.example',           3),
('org-northwind','Northwind Traders',   'NWT', '#10b981', 'helpdesk@northwind.example', 5),
('org-contoso',  'Contoso Financial',   'CNT', '#f59e0b', 'support@contoso.example',    2),
('org-fabrikam', 'Fabrikam Health',     'FBK', '#ef4444', 'itsm@fabrikam.example',      4);

INSERT INTO `tasks` (`id`, `org_id`, `title`, `description`, `category`, `priority`, `status`, `assignee`, `requester`, `due_date`, `created_at`, `updated_at`, `tags`) VALUES
('T-1042', 'org-acme',     'Replace failing RAID controller on file server', 'File server FS-02 in the Acme HQ datacenter is showing degraded RAID status. Schedule controller swap during maintenance window.', 'hardware', 'high',     'in_progress', 'Alex Rivera',  'Maria Chen',           DATE_ADD(CURDATE(), INTERVAL 1 DAY),  DATE_ADD(NOW(), INTERVAL -2 DAY), NOW(), '["server","critical-infrastructure"]'),
('T-1043', 'org-northwind','Provision 12 laptops for new hires', 'New cohort of 12 joining next Monday. Image laptops with standard SOE, enroll in Intune, prepare welcome kits.', 'hardware', 'medium',   'open',        'Jordan Park',  'HR Team',              DATE_ADD(CURDATE(), INTERVAL 4 DAY),  DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), '["onboarding","bulk"]'),
('T-1044', 'org-contoso',  'Investigate suspicious login from unknown region', 'Azure AD sign-in logs show admin account login from unusual IP. MFA passed. Need forensic review.', 'security', 'critical', 'open',        'Alex Rivera',  'SOC',                  CURDATE(),                            NOW(),                            NOW(),                            '["security","investigation"]'),
('T-1045', 'org-fabrikam', 'Update EHR application on clinical workstations', 'Roll out version 8.2.1 of EHR client to 48 clinical workstations across two sites after hours.', 'software', 'medium',   'in_progress', 'Priya Shah',   'Clinical Ops',         DATE_ADD(CURDATE(), INTERVAL 3 DAY),  DATE_ADD(NOW(), INTERVAL -3 DAY), NOW(), '["patching","clinical"]'),
('T-1046', 'org-acme',     'Reset MFA token for CFO', 'CFO lost their hardware token. Issue and register new token.', 'access', 'high', 'resolved', 'Jordan Park', 'Executive Assistant', DATE_ADD(CURDATE(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), '["mfa","vip"]'),
('T-1047', 'org-northwind','Wi-Fi dead zone in warehouse office', 'Users report intermittent drops. Schedule AP survey.', 'network', 'low', 'on_hold', 'Priya Shah', 'Warehouse Manager', DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL -5 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), '["wifi","survey"]'),
('T-1048', 'org-contoso',  'Create shared mailbox for investor relations', 'Provision shared mailbox ir@contoso.example with 5 delegates.', 'email', 'low', 'open', 'Jordan Park', 'Investor Relations', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NOW(), NOW(), '["exchange"]'),
('T-1049', 'org-fabrikam', 'Replace printer on 3rd floor nursing station', 'Toner and fuser failing repeatedly; replace unit.', 'hardware', 'medium', 'closed', 'Alex Rivera', 'Facilities', DATE_ADD(CURDATE(), INTERVAL -3 DAY), DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -3 DAY), '["printer"]');

INSERT INTO `incidents` (`id`, `org_id`, `title`, `summary`, `severity`, `status`, `impacted_service`, `reported_by`, `started_at`, `resolved_at`) VALUES
('INC-220', 'org-contoso',  'Core banking API latency > 8s', 'Transactions timing out for online banking customers. SRE engaged; scaling read replicas.', 'major', 'mitigating',    'Online Banking',  'Monitoring',   NOW(), NULL),
('INC-219', 'org-fabrikam', 'EHR integration queue backlog', 'Lab results delayed by 25 minutes. Vendor notified.', 'major', 'investigating', 'Lab Integration', 'Clinical Ops', NOW(), NULL),
('INC-218', 'org-acme',     'Intermittent VPN drops for remote staff', 'Resolved after failover to secondary concentrator.', 'minor', 'resolved', 'VPN', 'Helpdesk', DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY));

INSERT INTO `assets` (`id`, `org_id`, `name`, `type`, `serial_number`, `location`, `assigned_to`, `status`, `purchase_date`, `last_maintenance`, `next_maintenance`) VALUES
('AST-0001', 'org-acme',     'FS-02 File Server',              'server',  'DL380-ACM-0042',  'Acme HQ DC-1',        'Infrastructure Team', 'degraded',     '2021-06-15', '2025-11-01', '2026-05-01'),
('AST-0002', 'org-northwind','ThinkPad X1 Carbon (J.Park)',    'laptop',  'PF3K9821',        'NWT HQ',              'Jordan Park',         'operational',  '2024-02-10', '2025-12-15', '2026-06-15'),
('AST-0003', 'org-fabrikam', 'Clinical Workstation CW-317',    'laptop',  'CW-FBK-317',      'Ward 3B',             'Nursing Station',     'maintenance',  '2023-08-20', '2026-01-05', '2026-04-05'),
('AST-0004', 'org-contoso',  'Edge Router CNT-RTR-01',         'network', 'CSR-1000V-CNT01', 'Contoso DC-East',     'Network Team',        'operational',  '2022-11-11', '2025-10-20', '2026-04-20'),
('AST-0005', 'org-fabrikam', 'HP LaserJet M455 (3rd Floor)',   'printer', 'VNB3K12345',      'Nursing Station 3F',  'Shared',              'retired',      '2019-03-01', '2025-09-10', 'N/A');

INSERT INTO `activity` (`id`, `type`, `action`, `entity_title`, `org_id`, `actor`, `timestamp`) VALUES
('a1', 'task',        'created',   'Investigate suspicious login from unknown region', 'org-contoso',  'Alex Rivera',  NOW()),
('a2', 'incident',    'escalated', 'Core banking API latency > 8s',                    'org-contoso',  'SOC',          NOW()),
('a3', 'task',        'resolved',  'Reset MFA token for CFO',                          'org-acme',     'Jordan Park',  DATE_ADD(NOW(), INTERVAL -1 DAY)),
('a4', 'asset',       'updated',   'Clinical Workstation CW-317',                      'org-fabrikam', 'Priya Shah',   DATE_ADD(NOW(), INTERVAL -1 DAY)),
('a5', 'maintenance', 'scheduled', 'Edge Router CNT-RTR-01 firmware',                  'org-contoso',  'Network Team', DATE_ADD(NOW(), INTERVAL -2 DAY)),
('a6', 'task',        'opened',    'Provision 12 laptops for new hires',               'org-northwind','HR Team',      DATE_ADD(NOW(), INTERVAL -1 DAY));
