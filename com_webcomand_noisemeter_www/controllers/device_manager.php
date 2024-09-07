<?php
namespace com_webcomand_noisemeter_www\controllers;

use \io_comand_util\time;

/**
 * An MVC Controller to enable Device owners to manage their devices, including:
 * 
 * - Login with their email address and emailed link.
 * - View all devices currently registered to their email address.
 * - View Noise Measurements collected by each device.
 * - Set the location of a device.
 * - View Noise Measurements collected at each location.
 * - View Device History (for all history items associated with their email)
 * - Disable a device (stop collecting Noise Measurements at API).
 * - Enable and customize device inactivity notifications (sent to their email address).
 * - View any errors (event logs) associated with a device.
 * - Logout
 * 
 * The above is all accomplished in the PHP class below that uses standard PHP
 * libraries and the COMAND PHP API (https://www.webcomand.com/docs/api/php/).
 * 
 * More specifically:
 * - Web requests/responses use the cMVC Framework (https://www.webcomand.com/docs/api/php/io_comand_mvc/)
 *   and the Web Framework (https://www.webcomand.com/docs/api/php/io_comand_web/).
 * - Login/Logout features use the Login Framework (https://www.webcomand.com/docs/api/php/io_comand_login/).
 * - Database operations use the COMAND Repository (https://www.webcomand.com/docs/api/php/io_comand_repo/).
 * 
 */
class device_manager extends \io_comand_mvc\controller {
    const OUTLIER_SIZE_MIN = 20;
    const OUTLIER_SIZE_MAX = 30;

    const LOGIN_POLICY_OID = 748827; // tRacket Login Policy
    const LOGIN_FROM_EMAIL = 'tRacket Login <login@tracket.info>';
    const LOGIN_LINK_EXPIRATION_SECONDS = 900;
    const LOGIN_LINK_ISSUE = 'There was an issue creating an email login link.  An administrator has been notified.  Please try back again later.';
    const USER_TOKEN_CT = 'DeviceManagerUserToken';

    const SUPPORT_FROM_EMAIL = 'tRacket Account Manager <tracket-support@tracket.info>';
    const CHANGE_EMAIL_CT = 'ChangeEmailCode';
    
    const DEVICE_NAME = 'Sensor';
    const DEVICE_NAME_LC = 'sensor';

    private $login_framework = null;

    // Set up the webCOMAND Login Framework (https://www.webcomand.com/docs/api/php/io_comand_login/)
    private function login_framework($return_login = TRUE) {
        //static $login_framework = null; // used by require_login() and web__login()
        if(!$this->login_framework) {
            $policy = $this->repo()->get_object_by_oid(self::LOGIN_POLICY_OID, 'LoginPolicy'); // get the tRacket Login Policy (login rules and requirements)
            if(!$policy) {
                $this->show_error('Critical system error.  Login Policy not found.');
            }
            $this->login_framework = new \io_comand_login\login($policy, $this->repo());
        }
        return ($return_login ? $this->login_framework->login : $this->login_framework);
    }

    private function require_login() {
        // if the user is logged in, return the user
        $user = $this->login_framework()->is_logged_in();
        if($user) {
            return $user;
        }

        // if the user is not logged in, redirect them to the login page
        // (e.g. https://dm.tracket.info/device_manager/login)
        // We add the required query parameter to indicate that we were redirected,
        // so we can display an approriate message
        //$this->redirect($this->base_url . 'device_manager/login?required=1');
        $this->redirect($this->base_url . 'device_manager/login');
    }

    private function content_view(string $name, array $data = []) {
        // get the login form
        $content = $this->view($name, $data, TRUE);

        // display the login form in the published content wrapper
        return $this->view('content', [
            'page_class' => $name,
            'content' => $content
        ]);
    }

    /**
     * Display a list of devices, each in a block with a:
     * - Device ID
     * - Optional Name
     * - Recent Noise Measurements Graph (click to open all measurements)
     * - Optional Location (pin on map, click to add/edit)
     * - Option to display history
     * - Option to view event log
     * - Option to manage notifications
     * - Option to disable
     */
    public function web__index() {
        // get the user who is logge in, or redirect an unauthenticated user to login
        $user = $this->require_login();

        // get all devices currently associated with this user address
        $items = $this->repo()->get('FROM NoiseDeviceHistory WHERE User.OID=? AND RevisionEnd="0000-00-00 00:00:00" ORDER BY Device.Name, Device.OID', ['bind'=>[$user->OID]]);

        $devices = [];
        foreach($items as $item) {
            $device = $item->Device;
            $device->location = $item->NoiseLocation;
            $device->chart = $this->get_chart($item->Device);
            $device->map = $this->get_map($item->Device, $item->NoiseLocation);

            $last_measurement = $this->repo()->get_first('SELECT Timestamp FROM NoiseMeasurement WHERE User.OID=? AND Device.OID=? ORDER BY Timestamp DESC LIMIT 1', ['bind'=>[$user->OID, $item->Device->OID]]);
            if($last_measurement) {
                $device->last_measurement = time::format_timestamp_short($last_measurement->Timestamp);
                $device->last_measurement_stale = ((time::get_time() - time::db_to_int($last_measurement->Timestamp)) > (30 * 60));
            } else {
                $device->last_measurement = 'Not Available';
                $device->last_measurement_stale = TRUE;
            }
            $devices[] = $device;
        }

        // build the device list widget
        return $this->content_view('device_manager', [
            'base_url' => $this->base_url,
            'devices' => $devices,
            'user' => $user
        ]);
    }

    private function login_error($message = '', $email = '') {
        // get the login form
        return $this->content_view('login', [
            'base_url' => $this->base_url,
            'email' => $email,
            'message' => $message
        ]);
    }

    /**
     * Display the user login, which is just a simple form to enter your email address.
     * 
     * This method will also handles the emailed link to login.
     */
    public function web__login() {
        // If the user is already logged in, redirect to the device manager page
        if($this->login_framework()->is_logged_in()) {
            $this->redirect($this->base_url . 'device_manager');
        }

        // if this was requested from an email authentication link, validate the link code and attempt to login
        $link_code = $this->request->get('c');
        if($link_code) {
            // see if we can login with the code (it must match a valid User Token in the repository)
            try {
                // set the token to the link code for authentication
                $this->login_framework(FALSE)->set('token', $link_code);

                // set the remember flag if checked, so the user doesn't need to login again from this browser
                $link_remember = $this->request->get('r');
                $this->login_framework(FALSE)->set('remember', $link_remember);

                // user the login framework to login with the token and remember flag
                // NOTE: this will handle all security considerations configured in the login policy,
                // such as blocking IPs, User Agents and locking out users after too many failed attempts.
                $user = $this->login_framework()->login();
                if($user) {
                    // if we authenticated a user based on their token, we are now logged in and should:
                    
                    // 1. invalidate the login link/token
                    $token = $this->repo()->get_first('FROM ' . self::USER_TOKEN_CT . ' WHERE User.OID=? AND Token=? AND Active AND (ValidStart="0000-00-00 00:00:00" OR ValidStart<=NOW()) AND (ValidEnd="0000-00-00 00:00:00" OR ValidEnd>=NOW()) ORDER BY OID DESC', ['bind'=>[$user->OID, $link_code]]);
                    if($token) {
                        $token->Active = false;
                        if(!$token->approve(['VersionNotes' => 'Updated by successful account login.'])) {
                            // TODO: email an admin to let them know something went wrong (maybe limit number of these emails that are sent per day)
                        }
                    }

                    // 2. redirect the logged in user to the device manager.
                    $this->redirect($this->base_url . 'device_manager');
                }
                
                \comand::log_warning('Login link code (' . $link_code . ') worked, but login did not work.');
            } catch(\io_comand_login\exception $e) {
                // there was a login exception (bad token, account locked, etc.), report an error message,
                // but do not provide details that could be used to aid a malicious actor
                if($e->getCode() == \io_comand_login\exception::LOGIN_ERROR_SYSTEMLOCKED) {
                    return $this->login_error($e->getMessage());
                }
                
                \comand::log_warning('Login link code (' . $link_code . ') silent error: ' . $e->getMessage());
            }

            // if we couldn't get the user or report a more specific issue, display the generic login failure message
            return $this->login_error('The login link is not valid or has expired.  Please enter your email to try again.');
        }

        // if this was requested from the login form, validate the email address and if valid create a token and email the login link
        $email = $this->request->post('email');
        if($email) {
            // see if the email address is a valid email address (even if not for a user)
            if(!\io_comand_email\mail\address::is_email($email)) {
                return $this->login_error('Invalid email address provided.', $email);
            }

            // see if there is an active user with a matching email address
            $user = $this->repo()->get_first('FROM NoiseUser WHERE Email=? AND Active ORDER BY OID', ['bind'=>[$email]]);
            if(!$user) {
                \comand::log_warning("User login attempt with invalid email: $email");
                //return $this->login_error('If a valid device owner email address was provided, a login link was has been sent to your email address.');
                // display the login email sent page to let them know an email has been sent
                return $this->content_view('login_email_sent', [
                    'base_url' => $this->base_url,
                    'email' => $email
                ]);
            }

            // see if there is already an active token (maybe they never received or successfully used the previously sent email)
            $token = $this->repo()->get_first('FROM ' . self::USER_TOKEN_CT . ' WHERE User.OID=? AND Active AND (ValidStart="0000-00-00 00:00:00" OR ValidStart<=NOW()) AND (ValidEnd="0000-00-00 00:00:00" OR ValidEnd>=NOW()) ORDER BY OID DESC', ['bind'=>[$user->OID]]);
            if(!$token) {
                $token = $this->repo()->new_object(self::USER_TOKEN_CT);
                $token->User = $user;
                $token->ValidStart = time::get_db_timestamp();
                $token->ValidEnd = time::int_to_db(time::db_to_int($token->ValidStart) + self::LOGIN_LINK_EXPIRATION_SECONDS); // expire in 15 minutes
                if(!$token->approve(['VersionNotes' => 'Created by account manager login'])) {
                    // TODO: email an admin to let them know something went wrong (maybe limit number of these emails that are sent per day)
                    return $this->login_error(self::LOGIN_LINK_ISSUE);
                }
            }
            if($token->Token == '') {
                // TODO: email an admin to let them know something went wrong (maybe limit number of these emails that are sent per day)
                return $this->login_error(self::LOGIN_LINK_ISSUE);
            }

            $remember = $this->request->post('remember');
            $link = 'http' . ($this->request->server('HTTPS') ? 's' : '') . '://' . $this->request->server('SERVER_NAME') . $this->base_url . 'device_manager/login?c=' . $token->Token . ($remember ? '&r=1' : '');

            // process the tRacket Login Email Template with a link variable containing the link URL
            $sent = $this->send_template_email('a8b293ba-3b3c-11ef-9272-997273e8313c', $user->Email, ['link'=>$link]);
            if($sent) {
                \comand::log_notice("User login attempt with valid email: $email - Login email sent to " . $user->Username . " <" . $user->Email . ">.");
            } else {
                \comand::log_error("User login attempt with valid email: $email - Login email not sent: " . $log->as_html());
            }

            // display the login email sent page to let them know an email has been sent
            return $this->content_view('login_email_sent', [
                'base_url' => $this->base_url,
                'email' => $email
            ]);
        }

        $required = $this->request->get('required');
        if($required) {
            return $this->login_error('You must login to access the requested page.  If you just logged in, please make sure your browser accepts cookies, which are required to login.');
        }

        // get the login form
        return $this->content_view('login', [
            'base_url' => $this->base_url,
            'email' => $email
        ]);
    }

    private function send_template_email($uuid, $to, $variables = [], $options = []): bool {
        return \com_webcomand_noisemeter_www\libraries\email::send_template_email($this->repo(), $uuid, $to, $variables, $options);
    }

    // logout the user and take them to the login page
    public function web__logout() {
        $this->login_framework()->logout();
        $this->redirect($this->base_url . 'device_manager/login');
    }

    private function respond_with_error(string $message, array $options = []) {
        if($options['ajax'] ?? false) {
            $this->ajax->error($message);
        } else {
            $this->show_error($message);
        }
        exit();
    }

    /**
     * Validate the device is actively owned by the user making the request,
     * and get the corresponding user, device and history objects.
     * 
     * NOTE: if not valid, this method will show an error and never return.
     */
    private function validate_device_owner(int $device_oid, array $options = []) {
        // if the user is logged in, return the user
        $user = $this->login_framework()->is_logged_in();
        if(!$user) {
            $this->respond_with_error('You must be logged in.', $options);
        }

        // get the device object based on the OID
        $device = $this->repo()->get_first("SELECT Name FROM NoiseDevice WHERE OID=?", ['bind'=>[$device_oid]]);
        if(!$device) {
            $this->respond_with_error('Invalid ' . self::DEVICE_NAME . ' OID.', $options);
        }

        // make sure this device is currently owned by this user
        $active_history = $this->repo()->get_first("FROM NoiseDeviceHistory WHERE Device.OID=? AND User.OID=? AND RevisionEnd='0000-00-00 00:00:00'", ['bind'=>[$device->OID, $user->OID]]);
        if(!$active_history) {
            $this->respond_with_error(self::DEVICE_NAME . ' not associated with user.', $options);
        }

        return (object)['user'=>$user, 'device'=>$device, 'active_history' => $active_history];
    }

    public function web__update_device_name() {
        // get the updated name
        $device_oid = $this->request->post('oid');
        $device_name = $this->request->post('name');

        $info = $this->validate_device_owner($device_oid);
        $user = $info->user;
        $device = $info->device;

        if($device_name === FALSE || $device->Name == $device_name) {
            return $this->ajax->ok(self::DEVICE_NAME . ' Name Updated.');
        }

        $device->Name = $device_name;
        if(!$device->approve(['VersionNotes'=>'Updated Name from Account Manager.'])) {
            $this->show_error(self::DEVICE_NAME . ' could not be updated.');
        }

        $this->ajax->ok(self::DEVICE_NAME . ' Name Updated.');
    }

    public function web__device_notifications() {
        // get the device we are updating the location for
        $device_oid = $this->request->post('oid');
        if($device_oid === NULL || !is_numeric($device_oid)) {
            $this->show_error('Invalid Sensor OID.');
        }

        // validate the device is actively owned by the user making the request,
        // and get the corresponding user, device and history objects
        // NOTE: if not valid, this method will respond with an error and exit
        $info = $this->validate_device_owner($device_oid, ['ajax'=>true]);
        $user = $info->user;
        $device = $info->device;

        $notify = ($this->request->post('notify') === 'on');
        if($notify !== $device->Notify) {
            $device->Notify = $notify;
            if(!$device->approve(['VersionNotes'=>'Updated Notify from Account Manager.'])) {
                $this->show_error('Sensor could not be updated.');
            }
        }

        $this->ajax->ok(self::DEVICE_NAME . ' notifications updated.');
    }

    public function web__change_email() {
        $user = $this->require_login();

        // get the updated name
        $email = $this->request->post('email');

        // validate the email address
        if(!\io_comand_email\mail\address::is_email($email)) {
            return $this->ajax->error('Invalid email address.');
        }

        // generate and store a code with the desired email address, which can be used for verification
        $now = time();
        $change_email_code = $this->repo()->new_object(self::CHANGE_EMAIL_CT);
        $change_email_code->User = $user;
        $change_email_code->NewEmail = $email;
        $change_email_code->ValidStart = date('Y-m-d H:i:s', $now);
        $change_email_code->ValidEnd = date('Y-m-d H:i:s', $now + 3600); // 1 hour (60 minutes * 60 seconds = 3600 seconds)
        if(!$change_email_code->approve()) {
            return $this->ajax->error('Could not set up Change Email Code.');
        }

        $link = 'http' . ($this->request->server('HTTPS') ? 's' : '') . '://' . $this->request->server('SERVER_NAME') . $this->base_url . 'device_manager/verify_email?code=' . $change_email_code->Code;
        $sent = $this->send_template_email('bf0ef516-3b3e-11ef-9272-997273e8313c', $email, ['link'=>$link]);
        if(!$sent) {
            return $this->ajax->error('Verification email could not be sent.');
        }

        $this->ajax->ok('Email verification sent.');
    }

    public function web__verify_email() {
        $user = $this->login_framework()->is_logged_in();
        if(!$user) {
            $login_link = 'http' . ($this->request->server('HTTPS') ? 's' : '') . '://' . $this->request->server('SERVER_NAME') . $this->base_url . 'device_manager/login';
            $this->show_error('You must be logged in to verify your email address.  Please <a href="' . $login_link . '">login</a> and click the emailed verification link again.');
        }

        $code = $this->request->get('code');
        $now = date('Y-m-d H:i:s');
        $change_email_code = $this->repo()->get_first('FROM ' . self::CHANGE_EMAIL_CT . ' WHERE User.OID=? AND Code=? AND Active AND (ValidStart="0000-00-00 00:00:00" OR ValidStart<"' . $now . '") AND (ValidEnd="0000-00-00 00:00:00" OR ValidEnd>"' . $now . '")', ['bind'=>[$user->OID, $code]]);
        if($change_email_code === NULL) {
            $this->show_error('Invalid verification code.  It may have expired.  Please start the change email address process again.');
        }

        // validate and get the Change Email Code
        $email = $change_email_code->NewEmail;

        if(!\io_comand_email\mail\address::is_email($email)) {
            $this->show_error('Invalid email address.');
        }

        $user->Email = $email;
        if(!$user->approve(['VersionNotes'=>'Updated Email from Account Manager.'])) {
            $this->show_error('Email could not be updated.');
        }

        // we're done with the code, so deactivate it
        $change_email_code->Active = false;
        $change_email_code->approve(['Version Notes'=>'Email address verified.']);

        $this->redirect($this->base_url . 'device_manager');
    }

    public function web__update_device_location() {
        // get the device we are updating the location for
        $device_oid = $this->request->post('device_oid');

        // validate the device is actively owned by the user making the request,
        // and get the corresponding user, device and history objects
        // NOTE: if not valid, this method will respond with an error and exit
        $info = $this->validate_device_owner($device_oid, ['ajax'=>true]);
        $user = $info->user;
        $device = $info->device;
        $active_history = $info->active_history;

        // get the selected location (and validate the user owns it), or add a new one
        $location_oid = $this->request->post('location'); // selected location (empty string ("") for new)
        if($location_oid) {
            if($active_history->NoiseLocation && $active_history->NoiseLocation->OID == $location_oid) {
                // the selected location is the device's current active location, so use that and don't bother doing the extra work in the else below
                $location = $active_history->NoiseLocation;
            } else {
                // the selected location is a different existing location, so verify the user own's it or error
                $location = $this->get_user_location($user, $location_oid);
                if($location == null) {
                    return $this->ajax->error('Invalid location specified.');
                }
            }
        } else {
            // no location selected (it is new), so set up a new location in the same folder(s) as the active device history object
            $location = $this->repo()->new_object('NoiseLocation');
            $location->Folders = $active_history->Folders;
        }

        // set the location details
        $location->Label = $this->request->post('public_name');
        $location->PrivateLabel = $this->request->post('private_name');
        $location->Latitude = $this->request->post('lat');
        $location->Longitude = $this->request->post('lng');
        $location->Radius = $this->request->post('rad');

        // update/add the location
        $approved = $location->approve(['VersionNotes' => ($location_oid ? 'Updated' : 'Added') . ' in Account Manager.']);
        if(!$approved) {
            return $this->ajax->error('Could not approve location.');
        }

        // if there isn't an active location, or we are changing it, update the Device History
        if(!$active_history->NoiseLocation || $active_history->NoiseLocation->OID != $location->OID) {
            $now = time::get_db_timestamp();
            $active_history->RevisionEnd = $now;
            $approved = $active_history->approve(['VersionNotes' => 'Updated locaiton in Account Manager.']);
            if(!$approved) {
                return $this->ajax->error('Could not expire active ' . self::DEVICE_NAME_LC . ' history to update location.');
            }

            $new_history = $this->repo()->new_object("NoiseDeviceHistory");
            $new_history->Folders = $active_history->Folders;
            $new_history->RevisionStart = $now;
            $new_history->Device = $device;
            $new_history->User = $user;
            $new_history->NoiseLocation = $location;
            $approved = $new_history->approve(['VersionNotes' => 'Updated locaiton in Account Manager.']);
            if(!$approved) {
                return $this->ajax->error('Could not add active ' . self::DEVICE_NAME . ' history to update location.');
            }
        }

        return $this->ajax->ok('Updated location (OID ' . $location->OID . ').', ['data'=>['location_oid'=>$location->OID]]);
    }

    private function get_user_locations($user) {
        $locations = [];
        $items = $this->repo()->get("SELECT MIN(RevisionStart) AS FirstAppearance FROM NoiseDeviceHistory WHERE User.OID=? AND !ISNULL(NoiseLocation) GROUP BY NoiseLocation.OID ORDER BY FirstAppearance", ['bind'=>[$user->OID]]);
        foreach($items as $item) {
            $locations []= $item->NoiseLocation;
        }
        return $locations;
    }

    private function get_user_location($user, $location_oid) {
        $locations = $this->get_user_locations($user);
        foreach($locations as $loc) {
            if($loc->OID == $location_oid) {
                return $loc;
            }
        }
        return null;
    }

    private function get_user_location_by_ip() {
        // get the user's location based on their IP address
        $user_ip = $this->request->server('REMOTE_ADDR');
        $response = \io_comand_web\client::get('https://live.geoip1.webcomand.com/geo_ip_lookup?ip=' . $user_ip);
        if(!$response || !isset($response->data) || !$response->data->latitude || !$response->data->longitude) {
            // default to Toronto City Hall
            return (object)[
                'lat' => 43.6534,
                'lng' => -79.3839,
                'rad' => 50
            ];
        }

        // return the retreived lat/lng and accuracy radius (or 50 meters if not available)
        // NOTE: if accuracy radius provided, convert from kilometers to meters
        return (object)[
            'lat' => $response->data->latitude,
            'lng' => $response->data->longitude,
            'rad' => 0 //($response->data->accuracy_radius ? $response->data->accuracy_radius * 1000 : 50)
        ];
    }

    /**
     * Get the users device locations, as well as the "New Location" based on their IP address
     */
    public function web__device_locations() {
        // if the user is logged in, return the user
        $user = $this->login_framework()->is_logged_in();
        if(!$user) {
            $this->show_error('You must be logged in.');
        }

        // get the device
        $device_oid = $this->request->post('oid');

        // get the device object based on the OID
        $device = $this->repo()->get_first("SELECT Name FROM NoiseDevice WHERE OID=?", ['bind'=>[$device_oid]]);
        if(!$device) {
            $this->show_error('Invalid ' . self::DEVICE_NAME . ' OID.');
        }

        // make sure this device is currently owned by this user
        $device_history = $this->repo()->get_first("FROM NoiseDeviceHistory WHERE Device.OID=? AND User.OID=? AND RevisionEnd='0000-00-00 00:00:00'", ['bind'=>[$device->OID, $user->OID]]);
        if(!$device_history) {
            $this->show_error(self::DEVICE_NAME . ' not associated with user.');
        }

        $current_location = $device_history->NoiseLocation;

        $user_loc = $this->get_user_location_by_ip();
        $locations = [
            (object)[
                'oid' => '',
                'public_name' => 'New Location',
                'private_name' => '',
                'lat' => $user_loc->lat,
                'lng' => $user_loc->lng,
                'rad' => $user_loc->rad,
                'active' => ($current_location ? false : true)
            ]
        ];

        // get all locations associated with this user for any device
        $noise_locations = $this->get_user_locations($user);
        foreach($noise_locations as $loc) {
            $locations []= (object)[
                'oid' => $loc->OID,
                'public_name' => $loc->Label,
                'private_name' => $loc->PrivateLabel,
                'lat' => $loc->Latitude,
                'lng' => $loc->Longitude,
                'rad' => $loc->Radius,
                'active' => ($current_location && $current_location->OID == $loc->OID ? true : false)
            ];
        }
        $this->ajax->ok(['locations'=>$locations]);
    }

    /**
     * Get data for chart.
     */
    public function get_chart($device) {
        // get the most recent noise for this device
        $latest_noise = $this->repo()->get_first('SELECT Timestamp FROM NoiseMeasurement WHERE Device.OID=? ORDER BY Timestamp DESC LIMIT 1', ['bind'=>[$device->OID]]);
        if(!$latest_noise) {
            return '<p>No ' . self::DEVICE_NAME_LC . ' measurements available yet.</p>';
        }

        // get noise measurements from this device starting from the one latest received and going 24 hours back in time, up to 288 measurements (24 hours * 60 minutes / 5 minute intervals = 288 measurements per 24 hours)
        $noises = $this->repo()->get('SELECT Timestamp, Min, Max FROM NoiseMeasurement WHERE Device.OID=? AND Timestamp > DATE_SUB("' . $latest_noise->Timestamp . '", INTERVAL 1 DAY) ORDER BY Timestamp DESC LIMIT 288', ['bind'=>[$device->OID]]);
        if(!$noises || count($noises) == 0) {
            return '<p>No ' . self::DEVICE_NAME_LC . ' measurements available yet.</p>';
        }
        
        //return '<p>' . count($noises) . ' ' . self::DEVICE_NAME_LC . ' measurements available.</p>';

        // get min, max and outlier values and timestamps
        $timestamps = [];
        $min_values = [];
        $max_values = [];
        $mean_values = [];
        foreach($noises as $noise) {
            $timestamps []= $noise->Timestamp;
            $min_values []= $noise->Min;
            $max_values []= $noise->Max;
            $mean_values []= $noise->Mean;
        }

        //list($outlier_timestamps, $outlier_values, $outlier_sizes) = $this->outliers($timestamps, $max_values, 2);

        // convert arrays to strings that we can easily plug into our view, to keep the view as simple as possible
        return $this->view('device_chart', [
            'base_url' => $this->base_url,
            'device' => $device,
            'device_uuid' => \io_comand_util\uuid::binary_to_string($device->UUID),
            'timestamps' => '"' . join('","', $timestamps) . '"',
            'min_values' => join(',', $min_values),
            'max_values' => join(',', $max_values),
            'mean_values' => join(',', $mean_values),
            //'outlier_timestamps' => '"' . join('","', $outlier_timestamps) . '"',
            //'outlier_values' => join(',', $outlier_values),
            //'outlier_sizes' => join(',', $outlier_sizes)
        ], TRUE);
    }

    /**
     * Get map div for device and location.
     */
    public function get_map($device, $location) {
        return $this->view('device_map', [
            'base_url' => $this->base_url,
            'device' => $device,
            'device_uuid' => \io_comand_util\uuid::binary_to_string($device->UUID),
            'location' => $location
        ], TRUE);
    }

    private function get_device($device_id) {
        if(!$device_id) {
            return null;
        }

        return $this->repo()->get_first('FROM Device WHERE DeviceID=? ORDER BY DeviceID', [
            'bind'=>[$device_id]
        ]);
    }

    private function outliers($timestamps, $values, $magnitude = 1) {
        $count = count($values);

        // get the max
        $max = max($values);

        // Calculate the mean
        $mean = array_sum($values) / $count;

        // Calculate standard deviation and times by magnitude
        $deviation = sqrt(array_sum(array_map([$this, 'sd_square'], $values, array_fill(0, $count, $mean))) / $count) * $magnitude;
        $mean_plus_deviation = $mean + $deviation;
        $max_minus_mean_plus_deviation = $max - $mean_plus_deviation;

        // get outlier timestamps and values for all values that lie within $mean +- $deviation.
        $outlier_timestamps = [];
        $outlier_values = [];
        $outlier_sizes = [];
        for($i = 0; $i < $count; $i++) {
            $value = $values[$i];
            //if($value <= $mean - $deviation || $value >= $mean + $deviation) {
            if($value >= $mean_plus_deviation) {
                $outlier_timestamps []= $timestamps[$i];
                $outlier_values []= $value;
                $outlier_sizes []= self::OUTLIER_SIZE_MIN + (($value - $mean_plus_deviation) / $max_minus_mean_plus_deviation * self::OUTLIER_SIZE_MAX);
            }
        }

        return [$outlier_timestamps, $outlier_values, $outlier_sizes];
    }
    
    private function sd_square($value, $mean) {
        return pow($value - $mean, 2);
    } 
}
