<?php if(isset($location) && $location): ?>
    <div id="map-<?= $device_uuid ?>" class="device-map" data-label="<?= htmlspecialchars($location->Label) ?>" data-lat="<?= $location->Latitude ?>" data-lng="<?= $location->Longitude ?>" data-rad="<?= $location->Radius ?>"></div>
<?php else: ?>
    <div id="map-<?= $device_uuid ?>" class="device-map no-location"></div>
<?php endif; ?>
