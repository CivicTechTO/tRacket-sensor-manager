    <section class="nav">
        <h2 class="title">Sensors</h2>
        <ul class="app-nav">
            <li><a href="<?= $base_url ?>device_manager/logout">Logout</a></li>
        </ul>
    </section>

    <script type="text/javascript" src="/js/plotly.js"></script>
<?php foreach($devices as $device): ?>
    <section class="device" data-oid="<?= $device->OID ?>">
        <div class="details">
<?php if(false): ?>
            <div class="field id">
                <div class="label">ID</div>
                <div class="value"><span><?= $device->DeviceID ?></span></div>
            </div>
<?php endif; ?>
            <div class="field name">
                <div class="label">Name</div>
                <div class="value"><span><?= $device->Name ?></span><i title="Edit Sensor Name" class="edit" data-editor="device_name"></i></div>
            </div>
            <div class="field location">
                <div class="label">Location</div>
                <div class="value"><span><?= ($device->location ? $device->location->Label . '</span>' . ($device->location->PrivateLabel ? ' (<span>' . $device->location->PrivateLabel . '</span>)' : '') : '<em>unset</em>'); ?><i title="Edit Sensor Location" class="edit" data-editor="device_location"></i></div>
            </div>
            <div class="field notifications">
                <div class="label">Notifications</div>
                <div class="value"><span><?= ($device->Notify ? 'Enabled' : 'Disabled') ?></span><i title="Edit Sensor Notifications" class="edit" data-editor="device_notifications"></i></div>
            </div>
            <div class="field last-measurement<?= (!isset($device->last_measurement) || !$device->last_measurement || $device->last_measurement_stale ? ' attention' : ' success') ?>">
                <div class="label">Last Measurement</div>
                <div class="value"><?= $device->last_measurement ?></div>
            </div>
        </div>
        <?= $device->map ?>
        <?= $device->chart ?>
    </section>
<?php endforeach; ?>
    <section class="profile">
        <h2 class="title">Profile</h2>
        <div class="field">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:<?= $user->Email ?>"><?= $user->Email ?></a><i title="Edit Email Address" class="edit" data-editor="change_email"></i></div>
        </div>
    </section>
    <div class="popup_editor change_email" style="display: none;">
        <div class="content">
            <h3 class="titlebar">Change Email Address<i class="close"></i></h3>
            <form>
                <div id="change-email-help" class="help-text" style="display: block;">
                    <p>A new email address will need to be verified before it is updated.</p>
                </div>

                <label for="email_address" class="required">Email</label>
                <input id="email_address" name="email" type="text" placeholder="email@address.com" required />

                <button type="submit">Change</button>
            </form>
        </div>
    </div>
    <div class="popup_editor device_notifications" style="display: none;">
        <div class="content">
            <h3 class="titlebar">Edit Sensor Notifications<i class="close"></i></h3>
            <form>
                <input name="oid" type="hidden" value="" />
<?php if(true): ?>
                <div id="device-notifications-help" class="help-text" style="display: block;">
                    <p>Check to receive an email notification when the sensor has not reported a new measurement for more than 30 minutes, 1 day and 1 week.</p>
                </div>
                <label for="device-notifications" class="checkbox"><input id="device-notifications" name="notify" type="checkbox" /> Enable</label>
<?php else: ?>
                <label for="device_inactivity">Inactivity<i class="help" data-selector="#device-inactivity-help"></i></label>
                <div id="device-inactivity-help" class="help-text">
                    <p>Select an option below to receive a notification email when the sensor has not reported a new measurement for more than the selected time.</p>
                </div>
                <select id="device_inactivity" name="inactivity">
                    <option value="">None</option>
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                    <option value="3600">24 hours</option>
                </select>

                <label for="device_threshold">Noise Level<i class="help" data-selector="#device-threshold-help"></i></label>
                <div id="device-threshold-help" class="help-text">
                    <p>Select one or more options below to receive an email when noise levels at or above the selected level(s) are measured during the previous day.</p>
                    <p>Here are some recognized noise levels for guidance:</p>
                    <ul>
                        <li>40 dBA - max during night (8pm - 7am) <sup>WHO</sup></li>
                        <li>55 dBA - max during day (7am - 8pm) <sup>WHO</sup></li>
                        <li>85 dBA - harmful any time</li>
                    </ul>
                    <p>WHO - <a target="_blank" href="https://www.who.int/europe/news-room/fact-sheets/item/noise">World Health Organization Noise Guidelines</a></p>
                </div>
                <select id="device_threshold" name="threshold" multiple>
                    <option value="40">40 db (max during night)</option>
                    <option value="55">55 db (max during day)</option>
                    <option value="85">85 db (harmful any time)</option>
                </select>
<?php endif; ?>
                <button type="submit">Update</button>
            </form>
        </div>
    </div>

    <div class="popup_editor device_name" style="display: none;">
        <div class="content">
            <h3 class="titlebar">Edit Sensor Name<i class="close"></i></h3>
            <form>
                <input name="oid" type="hidden" value="" />

                <label for="device_name" class="required">Name</label>
                <input id="device_name" name="name" type="text" placeholder="Sensor Name" required />
    
                <button type="submit">Update</button>
            </form>
        </div>
    </div>

    <div class="popup_editor device_location" style="display: none;">
        <div class="content">
            <h3 class="titlebar">Edit Sensor Location<i class="help" data-selector="#device-location-help"></i><i class="close"></i></h3>
            <form>
                <div id="device-location-help" class="help-text">
                    <p>To set the sensor location:</p>
                    <ol>
                        <li>Select "New Location" or a previously added location to re-use or update.</li>
                        <li>Enter a Public Name others will see on the map.  For example, a cross street ("Mary St. & John Rd.") or point of interest ("North-East Berksy Park").</li>
                        <li>Enter a Private Name only you will see here in the Account Manager.</li>
                        <li>Set the location and privacy radius on the map.</li>
                    </ol>
                </div>

                <input name="device_oid" type="hidden" value="" />

                <select name="location">
                    <option value="">New Location</option>
                </select>

                <label for="device_public_name" class="required">Public Name<i class="help" data-selector="#public-name-help"></i></label>
                <div id="public-name-help" class="help-text">
                    <p>The Public Name will be associated with this location on the public tracket.info website map.</p>
                </div>
                <input id="device_public_name" name="public_name" type="text" placeholder="Location description for others" required />
    
                <label for="device_private_name">Private Name<i class="help" data-selector="#private-name-help"></i></label>
                <div id="private-name-help" class="help-text">
                    <p>The Private Name will be associated with this location in the Account Manager, and only visible to you.</p>
                </div>
                <input id="device_private_name" name="private_name" type="text" placeholder="Location description only for you" />

                <input name="lat" type="hidden" value="" />
                <input name="lng" type="hidden" value="" />
                <input name="rad" type="hidden" value="" />

                <label>Location with Privacy Radius<i class="help" data-selector="#location-help"></i></label>
                <div id="location-help" class="help-text">
                    <p>The Location and Privacy Radius identify the approximate location of the sensor with a radius out from that point that forms a circle that encompasses the accurate location.</p>
                    <p>A best practice to protect your privacy is to center the approximate location on a nearby cross-street or landmark, and then expand the privacy radius to encompase the accurate sensor location and potentially a bit further, until you are comfortable with the circle that others will see associated with the noise levels measured from the sensor.</p>
                    
                    <p>To set the location and privacy radius:</p>
                    <ol>
                        <li>Pan and zoom the map below so that the location you would like is near the center.  Use the search to center the map on an address (e.g. "1 Young St, Toronto") or latitude and longitude (e.g. "12.345, 45.678").</li>
                        <li>Select the circle button in the upper-left corner of the map.</li>
                        <li>Click or tap to set the location, and optionally drag out from the center to set the privacy radius.</li>
                        <li>Once the circle is on the map, you can adjust the location and privacy radius with the corresponding white boxes.</li>
                    </ol>
                </div>
                <div id="edit-location-map"></div>
    
                <button type="submit">Update</button>
            </form>
        </div>
    </div>
