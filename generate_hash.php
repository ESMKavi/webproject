<?php

// The password you want to use
$passwordToHash = 'staff789'; // <-- CHANGE THIS

// ... (the rest of the code is the same) ...
$newHash = password_hash($passwordToHash, PASSWORD_DEFAULT);
echo "<h1>New Password Hash for ranna_staff</h1>"; // (Optional: change title for clarity)
echo "<p>Your password is: <strong>" . htmlspecialchars($passwordToHash) . "</strong></p>";
echo "<p>Copy this new hash value below:</p>";
echo "<textarea rows='3' cols='70' readonly>" . htmlspecialchars($newHash) . "</textarea>";

?>

