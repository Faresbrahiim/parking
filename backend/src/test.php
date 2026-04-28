<?php

require_once "db.php";

if ($conn) {
    echo "DB CONNECTED ✔️";
} else {
    echo "DB FAILED ❌";
}
