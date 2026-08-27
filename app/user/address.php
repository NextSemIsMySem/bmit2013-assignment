<?php
require '../_base.php';
auth();

if (is_post()) {
    $action = req('action');
    $addressId = filter_var(req('address_id'), FILTER_VALIDATE_INT);
    $stm = $_db->prepare('SELECT address_id FROM address WHERE address_id = ? AND user_id = ?');
    $stm->execute([$addressId, $_user->user_id]);

    if (!$stm->fetchColumn()) {
        temp('info', 'Address not found.');
    } elseif ($action === 'default') {
        $_db->prepare('UPDATE address SET is_default = 0 WHERE user_id = ?')->execute([$_user->user_id]);
        $_db->prepare('UPDATE address SET is_default = 1 WHERE address_id = ? AND user_id = ?')->execute([$addressId, $_user->user_id]);
        temp('info', 'Default address updated.');
    } elseif ($action === 'delete') {
        $_db->prepare('DELETE FROM address WHERE address_id = ? AND user_id = ?')->execute([$addressId, $_user->user_id]);
        $defaultExists = $_db->prepare('SELECT COUNT(*) FROM address WHERE user_id = ? AND is_default = 1');
        $defaultExists->execute([$_user->user_id]);
        if (!$defaultExists->fetchColumn()) {
            $_db->prepare('UPDATE address SET is_default = 1 WHERE user_id = ? ORDER BY address_id LIMIT 1')->execute([$_user->user_id]);
        }
        temp('info', 'Address deleted.');
    }
    redirect('/user/address.php');
}

$addressStmt = $_db->prepare('SELECT * FROM address WHERE user_id = ? ORDER BY is_default DESC, address_id DESC');
$addressStmt->execute([$_user->user_id]);
$addresses = $addressStmt->fetchAll();

$_title = 'Shipping Addresses';
$_navSection = 'settings';
$_backUrl = '/user/settings.php';
$_backLabel = 'Back to Settings';
include '../_head.php';
?>

<p>Manage your saved shipping addresses.</p>
<p><a class="btn-green" href="/user/address-form.php">Add Shipping Address</a></p>

<?php if ($addresses): ?>
    <div class="address-list">
        <?php foreach ($addresses as $address): ?>
            <article class="address-card">
                <h3><?= encode($address->label) ?> <?php if ($address->is_default): ?><span class="address-default">Default</span><?php endif; ?></h3>
                <p><?= encode($address->street) ?><br><?= encode($address->city) ?>, <?= encode($address->state) ?> <?= encode($address->postal_code) ?><br><?= encode($address->country) ?></p>
                <div class="address-card-actions">
                    <a class="btn-gray" href="/user/address-form.php?edit=<?= (int) $address->address_id ?>">Edit</a>
                    <?php if (!$address->is_default): ?>
                        <form method="post"><input type="hidden" name="action" value="default"><input type="hidden" name="address_id" value="<?= (int) $address->address_id ?>"><button class="btn-blue" type="submit">Make Default</button></form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this address?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="address_id" value="<?= (int) $address->address_id ?>"><button class="btn-red" type="submit">Delete</button></form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No shipping addresses saved yet.</p>
<?php endif; ?>

<?php include '../_foot.php'; ?>
