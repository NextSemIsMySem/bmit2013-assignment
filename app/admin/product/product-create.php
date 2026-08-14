<?php
require '../../_base.php';
auth('admin');

$categories = $_db->query('SELECT category_id, name FROM category ORDER BY name')->fetchAll();
$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[$category->category_id] = $category->name;
}

$maxImages = 3;

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

    if (count($selectedFiles) < 1) {
        $_err['photo'] = 'Please add at least one picture.';
    } elseif (count($selectedFiles) > $maxImages) {
        $_err['photo'] = "You can only add up to $maxImages pictures per product.";
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
            'INSERT INTO product (category_id, product_name, price, weight, description, stock, availability)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $categoryId,
            $name,
            $price,
            $weight !== '' ? $weight : 0,
            $description,
            $stock,
            $availability,
        ]);

        $newProductId = $_db->lastInsertId();

        foreach ($newPhotos as $photo) {
            $stmt = $_db->prepare('INSERT INTO product_image (product_id, product_imageid) VALUES (?, ?)');
            $stmt->execute([$newProductId, $photo]);
        }

        temp('info', 'Product added.');
        redirect('products.php');
    }
}

$_title = 'Add Product';
include '../../_head.php';
?>

<form class="form" method="post" enctype="multipart/form-data">
    <?php html_select('category_id', 'Category', $categoryOptions, true); ?>
    <?php html_text('product_name', 'Product Name', 'text', true); ?>
    <?php html_text('price', 'Price (RM)', 'text', true); ?>
    <?php html_text('weight', 'Weight (kg)', 'text', true); ?>
    <label for="description">Description <span class="required-star">*</span></label>
    <button type="button" class="btn-dark" id="description-button">Insert Description</button>
    <textarea id="description" name="description" hidden><?= encode(req('description')) ?></textarea>
    <?= err('description') ?>
    <?php html_text('stock', 'Stock', 'text', true); ?>
    <?php html_select('availability', 'Availability', ['1' => 'Available', '0' => 'Unavailable'], true); ?>
    <section class="product-image-field">
        <label>Pictures <span class="required-star">*</span></label>
        <div class="product-image-list" id="product-image-list" data-max-images="<?= $maxImages ?>"></div>
        <button type="button" class="btn-dark" id="add-picture-button">Add Picture</button>
        <input type="file" id="photo" name="photos[]" accept="image/*" multiple hidden>
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
