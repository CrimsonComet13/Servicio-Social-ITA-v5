<?php
/**
 * Minimal TCPDF stub for local development.
 * This file does NOT implement full TCPDF. It's a lightweight shim that implements
 * a few methods used by the project's pdf/generator.php so the app won't fatal error
 * during includes. For production, replace this with the official TCPDF library.
 */

class TCPDF {
    protected $buffer = '';
    protected $meta = [];

    public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8') {
        // no-op constructor
    }

    public function SetCreator($c){ $this->meta['creator']=$c; }
    public function SetAuthor($a){ $this->meta['author']=$a; }
    public function SetTitle($t){ $this->meta['title']=$t; }
    public function SetSubject($s){ $this->meta['subject']=$s; }
    public function SetKeywords($k){ $this->meta['keywords']=$k; }

    public function setPrintHeader($v=true){}
    public function setPrintFooter($v=true){}
    public function SetMargins($l, $t, $r=null){}
    public function setAutoPageBreak($v, $margin=0){}
    public function AddPage(){ $this->buffer .= "\n\n----PAGE----\n"; }
    public function SetFont($family, $style='', $size=12){}

    /**
     * writeHTML - appends given HTML (stripped) to internal buffer
     */
    public function writeHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='') {
        // very naive strip tags
        $text = strip_tags($html);
        $this->buffer .= $text . "\n\n";
    }

    /**
     * Output - simplified: either returns binary PDF string or saves to file.
     * This implementation will create a very small PDF with plain text content.
     */
    public function Output($filename='document.pdf', $dest='I') {
        $content = $this->generatePdfFromText($this->buffer);
        if ($dest === 'F') {
            file_put_contents($filename, $content);
            return $filename;
        } elseif ($dest === 'S') {
            return $content;
        } else {
            // 'I' -> output to browser
            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="'.basename($filename).'"');
            }
            echo $content;
            return null;
        }
    }

    protected function generatePdfFromText($text) {
        // Build an extremely small PDF file embedding text (latin1). Not robust.
        $lines = explode("\n", trim($text));
        $stream = implode("\n", $lines);
        $pdf = "%PDF-1.1\n1 0 obj<<>>endobj\n2 0 obj<< /Length %d >>stream\n%s\nendstream endobj\ntrailer<<>>\n%%EOF";
        return sprintf($pdf, strlen($stream), $stream);
    }
}
?>