<?php
/**
 * DOMPDF Wrapper - Modernisierter Zugriff auf DOMPDF
 * Unterstützt sowohl alte (0.5.1) als auch neue (2.x) Version
 * 
 * Mit dieser Wrapper-Klasse ist die Upgrade vom alten auf das neue DOMPDF einfach
 */

class DOMPDF_Wrapper {
    private $dompdf;
    private $is_new_version = false;

    public function __construct() {
        // Versuche neue Version (2.x) zu laden
        if (file_exists(dirname(__FILE__) . '/dompdf/vendor/autoload.php')) {
            require_once dirname(__FILE__) . '/dompdf/vendor/autoload.php';
            $this->dompdf = new \Dompdf\Dompdf();
            $this->is_new_version = true;
        } 
        // Fallback auf alte Version (0.5.1)
        elseif (file_exists(dirname(__FILE__) . '/dompdf/dompdf_config.inc.php')) {
            require_once dirname(__FILE__) . '/dompdf/dompdf_config.inc.php';
            $this->dompdf = new DOMPDF();
            $this->is_new_version = false;
        } else {
            throw new Exception('DOMPDF Library nicht gefunden. Bitte aktualisieren Sie includes/user-reports/lib/dompdf/');
        }
    }

    /**
     * Setze Seitengröße
     */
    public function set_paper($size = 'letter', $orientation = 'landscape') {
        if ($this->is_new_version) {
            $this->dompdf->setPaper($size, $orientation);
        } else {
            $this->dompdf->set_paper($size, $orientation);
        }
        return $this;
    }

    /**
     * Lade HTML
     */
    public function load_html($html) {
        if ($this->is_new_version) {
            $this->dompdf->loadHtml($html);
        } else {
            $this->dompdf->load_html($html);
        }
        return $this;
    }

    /**
     * Rendern des PDFs
     */
    public function render() {
        if ($this->is_new_version) {
            $this->dompdf->render();
        } else {
            $this->dompdf->render();
        }
        return $this;
    }

    /**
     * Stream/Download des PDFs
     */
    public function stream($filename = 'document.pdf') {
        if ($this->is_new_version) {
            $this->dompdf->stream($filename);
        } else {
            $this->dompdf->stream($filename);
        }
        return $this;
    }

    /**
     * Gib DOMPDF Instanz zurück für direkten Zugriff
     */
    public function get_instance() {
        return $this->dompdf;
    }

    /**
     * Info über verwendete Version
     */
    public function get_version_info() {
        return [
            'new_version' => $this->is_new_version,
            'type' => $this->is_new_version ? 'DOMPDF 2.x' : 'DOMPDF 0.5.1'
        ];
    }
}

// Convenience Alias - benutze DOMPDF() statt DOMPDF_Wrapper()
if (!class_exists('DOMPDF', false)) {
    class DOMPDF extends DOMPDF_Wrapper {
        // Alias für Kompatibilität
    }
}
