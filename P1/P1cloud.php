<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["imatge_png"])) {
    
    // Comprova si el fitxer és una imatge de veritat
    $detalls_imatge = getimagesize($_FILES["imatge_png"]["tmp_name"]);
    if ($detalls_imatge === false) {
        echo "<p>El fitxer no és una imatge.</p>";
        $upload_ok = 0;
    } else {
        // Comprova la mida del fitxer
        if ($_FILES["imatge_png"]["size"] > 1000000) {
            echo "<p>Ho sento però el fitxer és massa gran.</p>";
            $upload_ok = 0;
        } else {
            // Permet només el format d'imatge PNG
            if ($detalls_imatge[2] != 3) {
                echo "<p>Ho sento però només es permeten imatges PNG.</p>";
                $upload_ok = 0;
            } else {
                $dimensions = $detalls_imatge[0] . 'x' . $detalls_imatge[1];
                $upload_ok = 1;
            }
        }
    }

    // Si no hi ha errors, processa la imatge
    if ($upload_ok == 1) {
        // Obre el procés per executar pngoptimizercl
        $proces = proc_open('pngoptimizercl -stdio -KeepFileDate', array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        ), $pipes, null, null);

        if (is_resource($proces)) {
            // Envia la imatge a stdin
            fwrite($pipes[0], file_get_contents($_FILES["imatge_png"]["tmp_name"]));
            fclose($pipes[0]);

            // Obté la imatge optimitzada d'stdout
            $imatge_optimitzada = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Tanca el procés
            proc_close($proces);
            
            // Modifica el nom del fitxer
            $nom_original_fitxer = pathinfo($_FILES["imatge_png"]["name"], PATHINFO_FILENAME);
            $nom_fitxer_optimitzat = $nom_original_fitxer . "-opt.png";
            
            // Detecta si la petició ve d'un navegador
            if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla/5.0') === 0) {
                // Navegador: envia HTML i permet descarregar la imatge optimitzada

                // Si hi ha cap missatge d'error, mostra'l
                $DEBUG = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                echo $DEBUG;
    
                // Obté les mides i el percentatge del resultat respecte a l'original
                $mida_original = $_FILES["imatge_png"]["size"];
                $mida_optimitzada = strlen($imatge_optimitzada);
                $percentatge = round($mida_optimitzada / $mida_original * 100, 0);

                // Mostra enllaç de descàrrega del resultat
                $data_uri = 'data:image/png;base64,' . base64_encode($imatge_optimitzada);
                echo "<p>Fitxer original: <b>" . $nom_original_fitxer . ".png</b> (" . $dimensions . ", " . $mida_original . " bytes)</p>";
                echo "<p>Fitxer optimitzat: <a href='$data_uri' download='$nom_fitxer_optimitzat'>$nom_fitxer_optimitzat</a> (" . $mida_optimitzada . " bytes, " . $percentatge . "%)</p>";
            } else {
                // No navegador: Envia la imatge directament com a fitxer descarregable
                header('Content-Type: image/png');
                header('Content-Disposition: attachment; filename="' . $nom_fitxer_optimitzat . '"');
                echo $imatge_optimitzada;
                exit;

                // Per fer servir aquest script amb curl:
                // curl -X POST -F "imatge_png=@latevaimatge.png" https://drake.udg.edu/pngoptimizer/ -J -O
            }
        } else {
            echo "<p>Ho sento però hi ha hagut un error en optimitzar la teva imatge.</p>";
        }
    }
} else {
    // Mostra el formulari de càrrega inicialment
    echo '<h2>Pujar imatge PNG i optimitzar-la</h2>';
    echo '<form action="/pngoptimizer/" method="post" enctype="multipart/form-data">';
    echo 'Selecciona una imatge PNG per optimitzar: <input type="file" name="imatge_png" id="imatge_png">';
    echo '<p><input type="submit" value="Optimitza PNG" name="submit"></p>';
    echo '</form>';
}