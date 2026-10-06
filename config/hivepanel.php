<?php

return [
    'version' => env('HIVEPANEL_VERSION', 'development'),
    'repository' => env('HIVEPANEL_REPOSITORY', 'HiveDevelopment/HivePanel'),
    'update_request_path' => env('HIVEPANEL_UPDATE_REQUEST_PATH', '/var/lib/hivepanel-host/update-request.json'),
    'update_status_path' => env('HIVEPANEL_UPDATE_STATUS_PATH', '/var/lib/hivepanel-host/update-status.json'),
];
