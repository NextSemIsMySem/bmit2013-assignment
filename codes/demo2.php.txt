<?php
require '../_base.php';

$_title = 'Page | Demo 2';
include '../_head.php';
?>
      
<button data-get="/">Index</button>
<button data-get="/page/demo1.php">Demo 1</button>
<button data-get="demo1.php">Demo 1</button>
<button data-get>Reload</button>
<span data-get="https://www.tarc.edu.my">TAR UMT</span>

<?php
include '../_foot.php';