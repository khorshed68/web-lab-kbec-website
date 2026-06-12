<?php
/**
 * GET /kbec/api/site_data.php
 * Returns public dynamic content: settings, announcements, team, sponsors, gallery
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    $settings = [];
    foreach ($db->query("SELECT setting_key,setting_value FROM site_settings")->fetchAll() as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    // Remove any sensitive keys just in case
    unset($settings['admin_password']);

    $announcements = $db->query("SELECT id,title,body,type,link,link_label FROM announcements WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
    $team          = $db->query("SELECT id,name,position,position_order,department,batch,email,linkedin,image FROM team_members WHERE is_active=1 ORDER BY position_order ASC")->fetchAll();
    $sponsors      = $db->query("SELECT id,name,logo,website,category,sort_order FROM sponsors WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
    $gallery       = $db->query("SELECT id,image_path,caption,category FROM gallery ORDER BY sort_order ASC, created_at DESC LIMIT 24")->fetchAll();

    echo json_encode([
        'ok'            => true,
        'settings'      => $settings,
        'announcements' => $announcements,
        'team'          => $team,
        'sponsors'      => $sponsors,
        'gallery'       => $gallery,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not load site data.']);
}
