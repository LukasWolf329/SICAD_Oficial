
<?php
    if(!isset($_SESSION)) {
        session_start();
    }
    
    if(!isset($_SESSION['ID'])) {
        //echo ("<script> alert('Você não pode acessar esta página porque não está logado.') </script>");
        header("Location: /(tabs)/(auth)/signin/page");
    }
?>