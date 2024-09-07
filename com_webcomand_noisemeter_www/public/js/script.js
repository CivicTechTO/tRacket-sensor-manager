$(document).ready(function(){
    var decibels_abbr = 'dBA'; // Decibels (dB) with "A" weighting.

    var current_popup = null;
    function show_popup(popup, edit_button) {
        popup.show();
        $('input[type="text"]',popup).first().focus().select();
    }
    function close_popup(popup) {
        popup.hide();
    }
    $('.popup_editor').on('click', function(event) {
        // if we clicked on the shaded out background (not the popup_editor contents), the close the popup
        if($(event.target).hasClass('popup_editor')) {
            close_popup($(event.target));
        }
    });
    $('.popup_editor .close').on('click', function() {
        close_popup($(this).closest('.popup_editor'));
    });

    function add_map(id, options) {
        var c = options['center'] ?? {lat: 0, lng: 0};
        var z = options['zoom'] ?? 12;
        var zc = options['zoomControl'] ?? true;
        var swz = options['scrollWheelZoom'] ?? true;
        var editable = options['editable'] ?? false;
        var marker = options['marker'] ?? null;
        var tiles = options['tiles'] ?? null;
        var search = options['search'] ?? false;

        // initialize the map on the "map" div with a given center and zoom
        var map = L.map(id, {
            center: [c.lat, c.lng],
            zoom: z,
            //zoomControl: zc,
            zoomControl: false, // we will add custom control in bottom-right instead
            scrollWheelZoom: swz,
            editable: editable
        });
        if(zc) {
            new L.Control.Zoom({position: 'bottomright'}).addTo(map);
        }
        if(editable) {
            L.EditControl = L.Control.extend({
                options: {
                    position: 'topleft',
                    callback: null,
                    kind: '',
                    html: ''
                },
                onAdd: function (map) {
                    var container = L.DomUtil.create('div', 'leaflet-control leaflet-bar');
                    var link = L.DomUtil.create('a', '', container);
                    link.href = '#';
                    link.title = 'Set a new location';
                    link.innerHTML = this.options.html;
                    L.DomEvent.on(link, 'click', L.DomEvent.stop)
                        .on(link, 'click', function () {
                            window.LAYER = this.options.callback.call(map.editTools);
                        }, this);

                    return container;
                }
            });

            L.NewCircleControl = L.EditControl.extend({
                options: {
                    position: 'topleft',
                    callback: function() {
                        map.editTools.startCircle()
                    },
                    kind: 'circle',
                    html: '⬤'
                }
            });

            map.addControl(new L.NewCircleControl());
        }

        if(tiles) {
            L.tileLayer(tiles.url, {
                maxZoom: tiles.maxZoom,
                attribution: tiles.attribution
            }).addTo(map);
        } else {
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);
        }

        var map_circle = null;
        function update_circle(point, options) {
            // if there is already a circle, remove it
            if(map_circle !== null) {
                map_circle.remove();
            }
            map_circle = L.circle(point, options).addTo(map);
            map.fitBounds(map_circle.getBounds(), { padding: [100, 100]});

            return map_circle;
        }
        function update_circle_with_layer(layer) {
            // if there is already a circle, remove it
            if(map_circle !== null) {
                map_circle.remove();
            }
            map_circle = layer;
        }
        function clear_circle() {
            // if there is a circle, remove it
            if(map_circle !== null) {
                map_circle.remove();
            }
            map_circle = null;
        }
        function get_circle() {
            return map_circle;
        }

        if(marker) {
            var map_marker = null;
            if(marker.circle) {
                map_marker = update_circle([marker.lat, marker.lng], {radius: marker.rad});
            } else {
            	map_marker = L.marker([marker.lat, marker.lng]).addTo(map);
            }
            if(marker.tooltip) {
                map_marker.bindTooltip(marker.tooltip);
            }
            if(marker.click) {
                map_marker.on('click', marker.click, map_marker);
            }
            if(marker.editable) {
                map_marker.enableEdit();
            }
        }

        if(search) {
            // add the location search
            L.Control.geocoder({
                defaultMarkGeocode: false,
                placeholder: 'Enter address or lat, lng...'
            }).on('markgeocode',function(e){
                update_circle(e.geocode.center, {radius: 20}).enableEdit();;
            }).addTo(map);
        }

        //return map;
        return {
            map: map,
            update_circle: update_circle,
            clear_circle: clear_circle,
            update_circle_with_layer: update_circle_with_layer,
            get_circle: get_circle
        };
    }

    (function init_map(){
        if($('#map').length == 0) {
            return;
        }

        var map_instance = add_map('map', {
            center: {lat: 43.65350815811275, lng: -79.38409699975287}, // Toronto City Hall
            zoom: 12,
            tiles: {url: 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png', maxZoom: 20, attribution: '&copy; <a href="https://stadiamaps.com/" target="_blank">Stadia Maps</a>'}
        });

        var wards = [];
        if(data && data.wards) {
            for(var w = 0; w < data.wards.length; w++) {
                const ward = data.wards[w];
                wards[ward.longcode] = {
                    title: ward.title,
                    longcode: ward.longcode,
                    //poly: L.polygon(ward.poly, {color: ward.color}).addTo(map_instance.map),
                    poly: L.polygon(ward.poly, {weight: 1, color: '#666666', opacity: 0.8, fillColor: '#666666', fillOpacity: 0.1}).addTo(map_instance.map),
                    avgs: [],
                    avg: null,
                    min: null,
                    max: null
                };
            }
        }
    
        var api_token = 'UPooYuLm4Zwu859Ehidgl5Ta19iuKTUG';
    
        $('#meters p').click(function() {
            $('#meters').toggleClass('active');
        });

        //const metersPerPixel = 156543.03392 * Math.cos(lat * Math.PI / 180) / Math.pow(2, zoom);
        var make_marker = function(data) {
            return L.circle([data.latitude, data.longitude], {
                color: '#666',
                weight: 1,
                fillColor: (data.active ? '#f80' : '#666'),
                fillOpacity: (data.active ? 0.8 : 0.5),
                radius: Math.max(50, data.radius)
            }).addTo(map_instance.map).on('click', function(){
                window.open('https://noise-dashboard-651f4e432386.herokuapp.com/locations/' + data.id, '_blank');
            });
        }

        $.ajax({
            url: 'https://api.tracket.info/v1/locations',
            success: function(data, textStatus, jqXHR) {
        		if(data.locations && data.locations.length>0) {
        			for(var i = 0; i < data.locations.length; i++) {
                        if(!data.locations[i].active) {
        				    make_marker(data.locations[i]);
                        }
        			}
            		for(var i = 0; i < data.locations.length; i++) {
                        if(data.locations[i].active) {
        				    make_marker(data.locations[i]);
                        }
        			}
        		}
        	},
        	error: function() {
        		console.log('ajax error');
        	}
        });
    })();

    (function init_device_maps(){
        var device_oid_to_map = []; // will be an associative array with device_oid key and map instance value
        var location_edit_map = null; // reused across popup instances

        function add_device_map(id, device_oid, circle) {
            return add_map(id, {
                center: {lat: circle.lat, lng: circle.lng},
                zoom: 17,
                scrollWheelZoom: false,
                marker: {
                    label: $(this).attr('data-label'),
                    lat: circle.lat,
                    lng: circle.lng,
                    rad: circle.rad,
                    tooltip: 'Click to edit.',
                    circle: true,
                    click: function(marker) {
                        edit.device_location({ data: { device_oid: device_oid } });
                    }
                }
            });
        }

        // define the different edit functions
        var edit = {
            device_name: function(e) {
                if(!e || !e.data || !e.data.device_oid) {
                    console.log('device_name() called without a device oid.');
                    return;
                }

                var field = $(this).closest('.field');
                var value = $('.value span', field);

                // get the device name
                var name = value.text();

                // display popup with current device name
                var popup = $('.device_name');
                var form = $('form', popup);

                var oid_input = $('input[name="oid"]', form);
                oid_input.val(e.data.device_oid);

                var name_input = $('input[name="name"]', form);
                name_input.val(name);

                form.off('submit').on('submit', function(event) {
                    event.preventDefault(); // Prevent the default form submission

                    // save new name if different
                    if(name_input.val() != name) {
                        name = name_input.val();
                        $.post('/device_manager/update_device_name', form.serialize(), function(data, textStatus, jqXHR) {
                            value.text(name);
                        }).fail(function() {
                            alert('Error updating device name.');
                        });
                    }

                    close_popup(popup);
                });
                show_popup(popup);
            },
            device_location: function(e) {
                if(!e || !e.data || !e.data.device_oid) {
                    console.log('device_location() called without a device oid.');
                    return;
                }
                
                // get the location field we are editing
                var field = $(this).closest('.field');
                var value = $('.value span', field);

                //var device_oid = (oid ? oid : $(this).attr('data-oid'));
                var device_oid = e.data.device_oid;
                var locations = [];
                var public_name = '';
                var private_name = '';
                var location_oid = 0;
                var lat = 0;
                var lng = 0;
                var rad = 0;

                // display popup with nothing populated
                var popup = $('.device_location');
                var form = $('form', popup);
                var location_selector = $('select[name="location"]', form);
                var device_oid_input = $('input[name="device_oid"]', form);
                var public_name_input = $('input[name="public_name"]', form);
                var private_name_input = $('input[name="private_name"]', form);
                var lat_input = $('input[name="lat"]', form);
                var lng_input = $('input[name="lng"]', form);
                var rad_input = $('input[name="rad"]', form);

                device_oid_input.val(device_oid);

                // reset the location selector and fields
                location_selector.find('option').remove().end().append('<option>Loading...</option>').val('');
                if(value.length>0) {
                    public_name_input.val(value.eq(0).text());
                    private_name_input.val(value.eq(1).text());
                } else {
                    public_name_input.val('');
                    private_name_input.val('');
                }

                function update_edit_location_map(options) {
                    // add/reset the map
                    if(location_edit_map == null) {
                        // the map is not set up yet, so do it now
                        var map_div = $('popup .location-map', this);
                        location_edit_map = add_map('edit-location-map', {
                            center: {lat: options.lat, lng: options.lng},
                            zoom: 17,
                            //zoomControl: false,
                            scrollWheelZoom: true,
                            editable: true,
                            search: true,
                            marker: (options.rad != 0 ? {
                                lat: options.lat,
                                lng: options.lng,
                                rad: options.rad,
                                circle: true,
                                editable: true
                            } : null),
                            //{url: 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png', maxZoom: 20, attribution: '&copy; <a href="https://stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'}
                            //tiles: {url: 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png', maxZoom: 20, attribution: '&copy; <a href="https://stadiamaps.com/" target="_blank">Stadia Maps</a>'}
                        });

                        // what to do when a new circle is created
                        location_edit_map.map.on('editable:created',  function(e) {
                            location_edit_map.clear_circle();
                        });

                        // what to do when a new circle is created
                        location_edit_map.map.on('editable:drawing:end',  function(e) {
                            location_edit_map.update_circle_with_layer(e.layer);
                        });
                    } else if(options.rad != 0) {
                        // the map is already set up, so just update the marker
                        location_edit_map.update_circle([options.lat, options.lng], {radius: options.rad}).enableEdit();
                    } else {
                        location_edit_map.clear_circle();
                    }
                }

                location_selector.off('change').on('change', function(){
                    // get the selected option and its values
                    var opt = location_selector.find(":selected");
                    location_oid = opt.val();

                    public_name_input.val(opt.attr('data-public_name'));
                    private_name_input.val(opt.attr('data-private_name'));

                    // add/update the map based on the selected location
                    update_edit_location_map({lat: opt.attr('data-lat'), lng: opt.attr('data-lng'), rad: opt.attr('data-rad')});
                });

                form.off('submit').on('submit', function(event) {
                    event.preventDefault(); // Prevent the default form submission

                    // get the lat, lng and rad from circle, if it exists
                    var circle = location_edit_map.get_circle();
                    if(circle == null) {
                        alert('Location and Privacy Radius must be set.');
                        return false;
                    }
                    if(public_name_input.val() == '') {
                        alert('Public Name must be set.');
                        return false;
                    }

                    // update hidden location fields with map circle info
                    lat_input.val(circle._latlng.lat);
                    lng_input.val(circle._latlng.lng);
                    rad_input.val(circle._mRadius);

                    // TODO: display spinner
                    $.post('/device_manager/update_device_location', form.serialize(), function(data, textStatus, jqXHR) {
                        // TODO: clear spinner
                        
                        if(jqXHR.status != 200) {
                            alert('Server error updating device location.');
                            return false;
                        }
                        if(data.Result && data.Result == 'ERROR') {
                            alert(data.Message);
                            return false;
                        }

                        close_popup(popup);

                        // update the device location title
                        var public_name = public_name_input.val();
                        var private_name = private_name_input.val();
                        var location_value = $('.device[data-oid=' + device_oid + '] .field.location .value');
                        location_value.contents().filter(function(){ return this.tagName != 'I'; }).remove();
                        location_value.prepend('<span>' + public_name + '</span>' + (private_name ? ' (<span>' + private_name + '</span>)' : ''));

                        var device_map_instance = device_oid_to_map[device_oid];
                        if(device_map_instance) {
                            // update the map/circle
                            var marker = device_map_instance.update_circle(circle._latlng, {radius: circle._mRadius});
                            marker.on('click', function() {
                                edit.device_location({ data: { device_oid: device_oid } });
                            });
                        } else {
                            // add the attributes
                            var device_map = $('.device[data-oid=' + device_oid + '] .device-map');
                            device_map.removeClass('no-location');
                            device_map.attr('data-lat', circle._latlng.lat);
                            device_map.attr('data-lng', circle._latlng.lng);
                            device_map.attr('data-rad', circle._mRadius);

                            // add the map/circle
                            device_oid_to_map[device_oid] = add_device_map(device_map.attr('id'), device_oid, {
                                lat: circle._latlng.lat,
                                lng: circle._latlng.lng,
                                rad: circle._mRadius
                            });
                        }
                    }).fail(function() {
                        // TODO: clear spinner
                        alert('Error updating device location.');
                    });
                });

                // disable the submit button until we populate the popup
                var submit_button = $('button[type="submit"]');
                submit_button.attr('disabled', '');

                show_popup(popup);

                // Populate popup fields and map with active location data, if any
                // TODO: AJAX request to get a list of this User's Locations, each with:
                // Public Name, Private Name, Latitude, Longitude, Radius, Active (true if the location is active for this device)
                $.ajax({
                    url: '/device_manager/device_locations',
                    method: 'post',
                    data: {
                        oid: device_oid
                    },
                    success: function(data, textStatus, jqXHR) {
                        location_selector.find('option').remove().end();
                        locations = [];
                        if(data.locations && data.locations.length>0) {
                            var active_loc_oid = ''; // default to "New Location", which has no OID
                			for(var i = 0; i < data.locations.length; i++) {
                                var loc = data.locations[i];
                                var option = $('<option>' + loc.public_name + (loc.private_name ? ' (' + loc.private_name + ')' : '') + '</option>').val(loc.oid)
                                    .attr('data-public_name', (loc.oid == '' ? '' : loc.public_name))
                                    .attr('data-private_name', loc.private_name)
                                    .attr('data-lat', loc.lat)
                                    .attr('data-lng', loc.lng)
                                    .attr('data-rad', loc.rad);
                                location_selector.append(option)
                                if(loc.active) {
                                    active_loc_oid = loc.oid;
                                }
                			}

                            // update value and trigger the map to update
                            location_selector.val(active_loc_oid).change();
                		}
                        submit_button.removeAttr('disabled');
                	},
                	error: function() {
                		alert('Error retreiving location information.');
                	}
                });
            },
            device_notifications:  function(e) {
                if(!e || !e.data || !e.data.device_oid) {
                    console.log('device_name() called without a device oid.');
                    return;
                }

                var field = $(this).closest('.field');
                var value = $('.value span', field);

                // get the notification state
                var notify = (value.text() == 'Enabled');

                var popup = $('.device_notifications');
                var form = $('form', popup);

                var oid_input = $('input[name="oid"]', form);
                oid_input.val(e.data.device_oid);

                var notify_input = $('input[name="notify"]', form);
                notify_input.prop("checked", notify);

                form.off('submit').on('submit', function(event) {
                    event.preventDefault(); // Prevent the default form submission

                    // save new name if different
                    if(notify_input.prop("checked") != notify) {
                        notify = notify_input.prop("checked");
                        $.post('/device_manager/device_notifications', form.serialize(), function(data, textStatus, jqXHR) {
                            value.text(notify ? 'Enabled' : 'Disabled');
                        }).fail(function(xhr) {
                            console.log(xhr);
                            if(xhr.responseJSON && xhr.responseJSON.message) {
                                alert('Error updating notifications: ' + xhr.responseJSON.message);
                            } else {
                                alert('Unknown error updating notifications.');
                            }
                        });
                    }

                    close_popup(popup);
                });
                show_popup(popup);
            },
            change_email: function(e) {
                var field = $(this).closest('.field');
                var value = $('.value', field);

                // get the email address
                var email = value.text();

                // display popup with current device name
                var popup = $('.change_email');
                var form = $('form', popup);

                var email_input = $('input[name="email"]', form);
                email_input.val(email);

                form.off('submit').on('submit', function(event) {
                    event.preventDefault(); // Prevent the default form submission

                    // save new name if different
                    if(email_input.val() != email) {
                        email = email_input.val();
                        $.post('/device_manager/change_email', form.serialize(), function(data, textStatus, jqXHR) {
                            // TODO: add a help bubble to explain that it must be verified before it will be updated
                            //value.text(email);
                            alert('Verification email sent.  Please check your inbox to complete the change.');
                        }).fail(function(xhr) {
                            console.log(xhr);
                            if(xhr.responseJSON && xhr.responseJSON.message) {
                                alert('Error changing email: ' + xhr.responseJSON.message);
                            } else {
                                alert('Unknown error changing email.');
                            }
                        });
                    }

                    close_popup(popup);
                });
                show_popup(popup);
            }
        };

        // bind the edit function to each DOM element with class="edit" and a valid data-func attribute
        $('.edit').each(function(index) {
            var func = $(this).attr('data-editor');
            if(edit[func]) {
                // get the device we are editing
                var device_oid = $(this).closest('.device').attr('data-oid');
                $(this).on('click', { device_oid: device_oid }, edit[func]);
            }
        });

        $('.help').each(function(index) {
            var selector = $(this).attr('data-selector');
            $(this).on('click', function() {
                $(selector).toggle('fast', function() {
                    if(location_edit_map) {
                        location_edit_map.map.invalidateSize();
                    }
                });
                return false;
            });
        });

        // load the map and marker for each device with a location set
        $('.device-map').each(function(index) {
            var device_oid = $(this).closest('.device').attr('data-oid');
            
            // if this device has no location, make box clickable and bail
            if($(this).hasClass('no-location')) {
                $(this).on('click', function() {
                    edit.device_location({ data: { device_oid: device_oid } });
                });
                return;
            }

            // if this device has a location, display the map with location
            device_oid_to_map[device_oid] = add_device_map($(this).attr('id'), device_oid, {
                lat: $(this).attr('data-lat'),
                lng: $(this).attr('data-lng'),
                rad: $(this).attr('data-rad')
            });
        });
    })();        
});
