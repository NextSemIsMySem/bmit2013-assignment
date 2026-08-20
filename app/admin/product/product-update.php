<?php
require '../../_base.php';
auth('admin');

$id = req('id');
$stmt = $_db->prepare('SELECT * FROM product WHERE product_id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('products.php');
}

$categories = $_db->query('SELECT category_id, name FROM category ORDER BY name')->fetchAll();
$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[$category->category_id] = $category->name;
}

$maxImages = 3;
$stmt = $_db->prepare('SELECT product_imageid FROM product_image WHERE product_id = ? ORDER BY product_imageid DESC');
$stmt->execute([$product->product_id]);
$images = $stmt->fetchAll();

if (is_post()) {
    $categoryId = req('category_id');
    $name = req('product_name');
    $price = req('price');
    $weight = req('weight');
    $description = req('description');
    $stock = req('stock');
    $availability = req('availability');

    if (!array_key_exists($categoryId, $categoryOptions)) {
        $_err['category_id'] = 'Please select a valid category.';
    }

    if ($name === '') {
        $_err['product_name'] = 'Product name is required.';
    } elseif (strlen($name) > 150) {
        $_err['product_name'] = 'Product name must be at most 150 characters.';
    }

    if ($price === '') {
        $_err['price'] = 'Price is required.';
    } elseif (!is_numeric($price) || (float) $price < 0) {
        $_err['price'] = 'Please enter a valid price.';
    }

    if ($weight === '') {
        $_err['weight'] = 'Weight is required.';
    } elseif (!is_numeric($weight) || (float) $weight < 0) {
        $_err['weight'] = 'Please enter a valid weight.';
    }

    if ($description === '') {
        $_err['description'] = 'Description is required.';
    }

    if ($stock === '') {
        $_err['stock'] = 'Stock is required.';
    } elseif (!ctype_digit($stock)) {
        $_err['stock'] = 'Please enter a valid stock quantity.';
    }

    if (!in_array($availability, ['0', '1'], true)) {
        $_err['availability'] = 'Please select availability.';
    }

    $newPhotos = [];
    $selectedFiles = [];
    $uploaded = $_FILES['photos'] ?? null;
    if ($uploaded && is_array($uploaded['name'])) {
        foreach ($uploaded['name'] as $i => $fileName) {
            if ($fileName === '' || $uploaded['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $selectedFiles[] = (object) [
                'type'     => $uploaded['type'][$i],
                'tmp_name' => $uploaded['tmp_name'][$i],
                'error'    => $uploaded['error'][$i],
                'size'     => $uploaded['size'][$i],
            ];
        }
    }

    if (count($images) + count($selectedFiles) < 1) {
        $_err['photo'] = 'Please add at least one picture.';
    } elseif (count($images) + count($selectedFiles) > $maxImages) {
        $_err['photo'] = "You can only have up to $maxImages pictures per product.";
    } else {
        foreach ($selectedFiles as $file) {
            if ($file->error !== UPLOAD_ERR_OK) {
                $_err['photo'] = 'One of the photos failed to upload. Please try again.';
                break;
            } elseif (!str_starts_with($file->type, 'image/')) {
                $_err['photo'] = 'Photos must be image files.';
                break;
            } elseif ($file->size > 1 * 1024 * 1024) {
                $_err['photo'] = 'Each photo must be 1MB or smaller.';
                break;
            }
        }
    }

    if (!$_err) {
        foreach ($selectedFiles as $file) {
            $newPhotos[] = save_photo($file, __DIR__ . '/../../photos');
        }
    }

    if (!$_err) {
        $stmt = $_db->prepare(
            'UPDATE product
             SET category_id = ?, product_name = ?, price = ?, weight = ?, description = ?, stock = ?, availability = ?
             WHERE product_id = ?'
        );
        $stmt->execute([
            $categoryId,
            $name,
            $price,
            $weight !== '' ? $weight : 0,
            $description,
            $stock,
            $availability,
            $id,
        ]);

        foreach ($newPhotos as $photo) {
            $stmt = $_db->prepare('INSERT INTO product_image (product_id, product_imageid) VALUES (?, ?)');
            $stmt->execute([$id, $photo]);
        }

        if ((int) $stock <= 0) {
            // Back to 0 stock — clear the "already notified" flag so a future
            // restock notifies these users again instead of staying silent.
            $resetShown = $_db->prepare('UPDATE stock_reminder SET shown = 0 WHERE product_id = ?');
            $resetShown->execute([$id]);
        }

        temp('info', 'Product updated.');

        if ((int) $stock > 0 && (int) $stock !== (int) $product->stock && $availability === '0') {
            temp('activate_prompt', ['id' => $id, 'name' => $name]);
            redirect('product-update.php?id=' . $id);
        }

        redirect('products.php');
    }
} else {
    // Pre-fill sticky form fields from the DB on first (GET) load.
    $_REQUEST['category_id'] = $product->category_id;
    $_REQUEST['product_name'] = $product->product_name;
    $_REQUEST['price'] = $product->price;
    $_REQUEST['weight'] = $product->weight;
    $_REQUEST['description'] = $product->description;
    $_REQUEST['stock'] = $product->stock;
    $_REQUEST['availability'] = (string) (int) $product->availability;
}

$_title = 'Update Product';
include '../../_head.php';
?>

<form class="form" method="post" enctype="multipart/form-data">
    <?php html_select('category_id', 'Category', $categoryOptions, true); ?>
    <?php html_text('product_name', 'Product Name', 'text', true); ?>
    <?php html_text('price', 'Price (RM)', 'text', true); ?>
    <?php html_text('weight', 'Weight (kg)', 'text', true); ?>
    <label for="description">Description <span class="required-star">*</span></label>
    <button type="button" class="btn-dark" id="description-button">Modify Description</button>
    <textarea id="description" name="description" hidden><?= encode(req('description')) ?></textarea>
    <?= err('description') ?>
    <?php html_text('stock', 'Stock', 'text', true); ?>
    <?php html_select('availability', 'Availability', ['1' => 'Available', '0' => 'Unavailable'], true); ?>
    <section class="product-image-field">
        <label>Pictures <span class="required-star">*</span></label>
        <div class="product-image-list" id="product-image-list" data-max-images="<?= $maxImages ?>">
            <?php foreach ($images as $image): ?>
                <figure class="product-image-list__item">
                    <img src="/photos/<?= encode($image->product_imageid) ?>" alt="">
                    <button
                        type="button"
                        class="round-delete-button"
                        data-post="product-image-delete.php?id=<?= encode($product->product_id) ?>&image=<?= encode($image->product_imageid) ?>"
                        data-confirm="Remove this picture?"
                        aria-label="Remove picture"
                        title="Remove picture"
                    >
                        <img src="/images/delete.png" alt="">
                    </button>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php if (count($images) < $maxImages): ?>
            <button type="button" class="btn-dark" id="add-picture-button">Add Picture</button>
            <input type="file" id="photo" name="photos[]" accept="image/*" multiple hidden>
        <?php else: ?>
            <p class="field-note">Maximum pictures reached. Remove a photo to insert new ones.</p>
        <?php endif; ?>
        <?= err('photo') ?>
    </section>
    <section class="buttons">
        <button type="submit" class="btn-green">Save</button>
        <button type="button" class="btn-dark" data-get="products.php">Cancel</button>
    </section>
</form>

<dialog id="description-dialog" aria-labelledby="description-dialog-title">
    <p id="description-dialog-title">Description</p>
    <textarea id="description-input" rows="8"></textarea>
    <div class="description-dialog-actions">
        <button type="button" class="btn-dark" id="description-cancel">Cancel</button>
        <button type="button" class="btn-green" id="description-save">Save</button>
    </div>
</dialog>

<?php
include '../../_foot.php';
