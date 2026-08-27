<?php
require '../_base.php';
auth();

function address_input($name, $label, $value = '') {
    echo '<label for="address-' . encode($name) . '">' . encode($label) . '</label>';
    echo '<input id="address-' . encode($name) . '" name="' . encode($name) . '" value="' . encode($value) . '">';
    echo err('address_' . $name);
}

$addressId = filter_var($_REQUEST['address_id'] ?? $_GET['edit'] ?? '', FILTER_VALIDATE_INT);
$editAddress = null;
if ($addressId) {
    $stm = $_db->prepare('SELECT * FROM address WHERE address_id = ? AND user_id = ?');
    $stm->execute([$addressId, $_user->user_id]);
    $editAddress = $stm->fetch();
    if (!$editAddress) redirect('/user/address.php');
}

if (is_post()) {
    $label = req('label');
    $street = req('street');
    $city = req('city');
    $state = req('state');
    $postalCode = req('postal_code');
    $country = req('country');
    $latitude = req('latitude');
    $longitude = req('longitude');
    $isDefault = req('is_default') === '1';

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
        redirect('/user/address.php');
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
$_backUrl = '/user/address.php';
$_backLabel = 'Back to Addresses';
$_googleMaps = true;
include '../_head.php';
?>

<p>Choose a location on the map or search for your shipping address.</p>
<form class="form address-form" method="post">
    <input type="hidden" name="address_id" value="<?= $addressId ? (int) $addressId : '' ?>">
    <div class="address-map-picker">
        <label for="address-search">Choose Location</label>
        <input id="address-search" type="search" placeholder="Search for an address on Google Maps" autocomplete="off">
        <div id="address-map" data-latitude="<?= encode($editAddress->latitude) ?>" data-longitude="<?= encode($editAddress->longitude) ?>"></div>
        <?= err('address_location') ?>
    </div>
    <?php address_input('label', 'Address Label', $editAddress->label); ?>
    <?php address_input('street', 'Street Address', $editAddress->street); ?>
    <?php address_input('city', 'City', $editAddress->city); ?>
    <?php address_input('state', 'State', $editAddress->state); ?>
    <?php address_input('postal_code', 'Postal Code', $editAddress->postal_code); ?>
    <?php address_input('country', 'Country', $editAddress->country); ?>
    <input type="hidden" id="address-latitude" name="latitude" value="<?= encode($editAddress->latitude) ?>">
    <input type="hidden" id="address-longitude" name="longitude" value="<?= encode($editAddress->longitude) ?>">
    <label class="checkbox-label"><input type="checkbox" name="is_default" value="1" <?= $editAddress->is_default ? 'checked' : '' ?>> Make this my default shipping address</label>
    <section class="buttons"><button class="btn-green" type="submit"><?= $addressId ? 'Update Address' : 'Add Address' ?></button><a class="btn-gray" href="/user/address.php">Cancel</a></section>
</form>

<?php include '../_foot.php'; ?>
