<?php
require '../_base.php';
auth('member');

function google_address_from_place($placeId) {
    $apiKey = google_maps_api_key();
    if ($apiKey === '' || $placeId === '') return null;

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'place_id' => $placeId,
        'key' => $apiKey,
    ]);
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 5],
    ]));
    $data = $response === false ? null : json_decode($response, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) return null;

    $components = [];
    foreach ($data['results'][0]['address_components'] ?? [] as $component) {
        foreach ($component['types'] as $type) {
            $components[$type] = $component['long_name'];
        }
    }

    $street = trim(($components['street_number'] ?? '') . ' ' . ($components['route'] ?? ''));
    if ($street === '' || empty($components['postal_code']) || empty($components['country'])) return null;

    $location = $data['results'][0]['geometry']['location'] ?? [];
    if (!isset($location['lat'], $location['lng'])) return null;

    return [
        'street' => $street,
        'city' => $components['locality'] ?? $components['postal_town'] ?? $components['administrative_area_level_2'] ?? '',
        'state' => $components['administrative_area_level_1'] ?? '',
        'postal_code' => $components['postal_code'],
        'country' => $components['country'],
        'latitude' => (string) $location['lat'],
        'longitude' => (string) $location['lng'],
    ];
}

function google_address_from_location($latitude, $longitude) {
    $apiKey = google_maps_api_key();
    if ($apiKey === '' || !is_numeric($latitude) || !is_numeric($longitude)) return null;

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'latlng' => $latitude . ',' . $longitude,
        'key' => $apiKey,
    ]);
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 5],
    ]));
    $data = $response === false ? null : json_decode($response, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) return null;

    $result = $data['results'][0];
    $components = [];
    foreach ($result['address_components'] ?? [] as $component) {
        foreach ($component['types'] as $type) {
            $components[$type] = $component['long_name'];
        }
    }

    $street = trim(($components['street_number'] ?? '') . ' ' . ($components['route'] ?? ''));
    $location = $result['geometry']['location'] ?? [];
    if ($street === '' || empty($components['postal_code']) || empty($components['country']) || !isset($location['lat'], $location['lng'])) return null;

    return [
        'street' => $street,
        'city' => $components['locality'] ?? $components['postal_town'] ?? $components['administrative_area_level_2'] ?? '',
        'state' => $components['administrative_area_level_1'] ?? '',
        'postal_code' => $components['postal_code'],
        'country' => $components['country'],
        'latitude' => (string) $location['lat'],
        'longitude' => (string) $location['lng'],
    ];
}

function address_input($name, $label, $value = '', $readonly = false) {
    echo '<label for="address-' . encode($name) . '">' . encode($label) . '</label>';
    echo '<input id="address-' . encode($name) . '" name="' . encode($name) . '" value="' . encode($value) . '"' . ($readonly ? ' readonly' : '') . '>';
    echo err('address_' . $name);
}

$addressId = filter_var($_REQUEST['address_id'] ?? $_GET['edit'] ?? '', FILTER_VALIDATE_INT);
$returnUrl = safe_local_url(req('return'), '/user/address.php');
$editAddress = null;
if ($addressId) {
    $stm = $_db->prepare('SELECT * FROM address WHERE address_id = ? AND user_id = ? AND deleted_at IS NULL');
    $stm->execute([$addressId, $_user->user_id]);
    $editAddress = $stm->fetch();
    if (!$editAddress) redirect($returnUrl);
}

if (is_post()) {
    verify_csrf();
    $label = req('label');
    $street = req('street');
    $city = req('city');
    $state = req('state');
    $postalCode = req('postal_code');
    $country = req('country');
    $latitude = req('latitude');
    $longitude = req('longitude');
    $placeId = req('place_id');
    $isDefault = req('is_default') === '1';

    // Existing addresses may not have a place ID stored. Re-verify their
    // saved map coordinates instead of requiring the user to select again.
    $googleAddress = $placeId !== ''
        ? google_address_from_place($placeId)
        : google_address_from_location($latitude, $longitude);
    if (!$googleAddress) {
        $_err['address_location'] = 'Please choose a real address from Google Maps.';
    } else {
        $street = $googleAddress['street'];
        $city = $googleAddress['city'];
        $state = $googleAddress['state'];
        $postalCode = $googleAddress['postal_code'];
        $country = $googleAddress['country'];
        $latitude = $googleAddress['latitude'];
        $longitude = $googleAddress['longitude'];
    }

    foreach ([
        'label' => [$label, 50, 'Address label'],
        'street' => [$street, 255, 'Street address'],
        'city' => [$city, 100, 'City'],
        'state' => [$state, 100, 'State'],
        'postal_code' => [$postalCode, 20, 'Postal code'],
        'country' => [$country, 100, 'Country'],
    ] as $name => [$value, $max, $description]) {
        if ($value === '') $_err['address_' . $name] = $description . ' is required.';
        elseif (strlen($value) > $max) $_err['address_' . $name] = $description . ' must be at most ' . $max . ' characters.';
    }
    if (($latitude !== '' && !is_numeric($latitude)) || ($longitude !== '' && !is_numeric($longitude))) {
        $_err['address_location'] = 'Please choose a valid location on the map.';
    }

    if (!$_err) {
        $_db->beginTransaction();
        $_db->prepare('SELECT user_id FROM user WHERE user_id = ? FOR UPDATE')->execute([$_user->user_id]);
        if ($isDefault || !$addressId) {
            $_db->prepare('UPDATE address SET is_default = 0 WHERE user_id = ?')->execute([$_user->user_id]);
        }
        if ($addressId) {
            $stm = $_db->prepare('UPDATE address SET label = ?, street = ?, city = ?, state = ?, postal_code = ?, country = ?, latitude = NULLIF(?, \'\'), longitude = NULLIF(?, \'\'), is_default = ? WHERE address_id = ? AND user_id = ?');
            $stm->execute([$label, $street, $city, $state, $postalCode, $country, $latitude, $longitude, $isDefault ? 1 : 0, $addressId, $_user->user_id]);
        } else {
            $stm = $_db->prepare('INSERT INTO address (user_id, label, street, city, state, postal_code, country, latitude, longitude, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?)');
            $stm->execute([$_user->user_id, $label, $street, $city, $state, $postalCode, $country, $latitude, $longitude, $isDefault ? 1 : 0]);
        }
        $_db->commit();
        temp('info', $addressId ? 'Address updated.' : 'Address added.');
        redirect($returnUrl);
    }

    $editAddress = (object) compact('label', 'street', 'city', 'state', 'postalCode', 'country', 'latitude', 'longitude', 'isDefault');
    $editAddress->postal_code = $postalCode;
    $editAddress->is_default = $isDefault;
}

if (!$editAddress) {
    $editAddress = (object) ['label' => 'Address', 'street' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '', 'latitude' => '', 'longitude' => '', 'is_default' => false];
}

$_title = $addressId ? 'Edit Shipping Address' : 'Add Shipping Address';
$_navSection = 'settings';
$_backUrl = $returnUrl;
$_backLabel = str_starts_with($returnUrl, '/cart/checkout.php') ? 'Back to Checkout' : 'Back to Addresses';
$_googleMaps = true;
include '../_head.php';
?>

<p>Choose a location on the map or search for your shipping address.</p>
<form class="form address-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="address_id" value="<?= $addressId ? (int) $addressId : '' ?>">
    <input type="hidden" name="return" value="<?= encode($returnUrl) ?>">
    <input type="hidden" id="address-place-id" name="place_id" value="">
    <div class="address-map-picker">
        <label for="address-search">Choose Location</label>
        <input id="address-search" type="search" placeholder="Search for an address on Google Maps" autocomplete="off">
        <div id="address-map" data-latitude="<?= encode($editAddress->latitude) ?>" data-longitude="<?= encode($editAddress->longitude) ?>"></div>
        <?= err('address_location') ?>
    </div>
    <?php address_input('label', 'Address Label', $editAddress->label); ?>
    <?php address_input('street', 'Street Address', $editAddress->street, true); ?>
    <?php address_input('city', 'City', $editAddress->city, true); ?>
    <?php address_input('state', 'State', $editAddress->state, true); ?>
    <?php address_input('postal_code', 'Postal Code', $editAddress->postal_code, true); ?>
    <?php address_input('country', 'Country', $editAddress->country, true); ?>
    <input type="hidden" id="address-latitude" name="latitude" value="<?= encode($editAddress->latitude) ?>">
    <input type="hidden" id="address-longitude" name="longitude" value="<?= encode($editAddress->longitude) ?>">
    <label class="checkbox-label"><input type="checkbox" name="is_default" value="1" <?= $editAddress->is_default ? 'checked' : '' ?>> Make this my default shipping address</label>
    <section class="buttons"><button class="btn-green" type="submit"><?= $addressId ? 'Update Address' : 'Add Address' ?></button><a class="btn-gray" href="<?= encode($returnUrl) ?>">Cancel</a></section>
</form>

<?php include '../_foot.php'; ?>
