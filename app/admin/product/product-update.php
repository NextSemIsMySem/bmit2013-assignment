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

    if ($weight !== '' && (!is_numeric($weight) || (float) $weight < 0)) {
        $_err['weight'] = 'Please enter a valid weight.';
    }

    if ($stock === '') {
        $_err['stock'] = 'Stock is required.';
    } elseif (!ctype_digit($stock)) {
        $_err['stock'] = 'Please enter a valid stock quantity.';
    }

    if (!in_array($availability, ['0', '1'], true)) {
        $_err['availability'] = 'Please select availability.';
    }

    $newPhoto = null;
    $f = get_file('photo');
    if ($f) {
        if (count($images) >= $maxImages) {
            $_err['photo'] = "You can only add up to $maxImages pictures. Remove one first.";
        } elseif (!str_starts_with($f->type, 'image/')) {
            $_err['photo'] = 'Photo must be an image file.';
        } elseif ($f->size > 1 * 1024 * 1024) {
            $_err['photo'] = 'Photo must be 1MB or smaller.';
        } else {
            $newPhoto = save_photo($f, __DIR__ . '/../../photos');
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

        if ($newPhoto) {
            $stmt = $_db->prepare('INSERT INTO product_image (product_id, product_imageid) VALUES (?, ?)');
            $stmt->execute([$id, $newPhoto]);
        }

        temp('info', 'Product updated.');
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
    <?php html_select('category_id', 'Category', $categoryOptions); ?>
    <?php html_text('product_name', 'Product Name'); ?>
    <?php html_text('price', 'Price (RM)'); ?>
    <?php html_text('weight', 'Weight (kg)'); ?>
    <label for="description">Description</label>
    <textarea id="description" name="description"><?= encode(req('description')) ?></textarea>
    <?= err('description') ?>
    <?php html_text('stock', 'Stock'); ?>
    <?php html_select('availability', 'Availability', ['1' => 'Available', '0' => 'Unavailable']); ?>
    <section class="product-image-field">
        <label>Pictures</label>
        <div class="product-image-list">
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
            <?php if (count($images) < $maxImages): ?>
                <label class="product-image-list__add" tabindex="0">
                    <?= html_file('photo', 'image/*', 'hidden') ?>
                    <img src="/images/add-picture.png" data-src="/images/add-picture.png" alt="Add picture">
                </label>
            <?php endif; ?>
        </div>
        <?= err('photo') ?>
    </section>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="button" data-get="products.php">Cancel</button>
    </section>
</form>

<?php
include '../../_foot.php';
