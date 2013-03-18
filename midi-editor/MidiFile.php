<?php

class MidiFile {
    private $header = null;
    private $tracks = null;

    public function load($filename) {
        $binaryMidi = file_get_contents($filename);
        $this->parse($binaryMidi);
    }

    public function parse($binaryMidi) {
        $this->header = null;
        $this->tracks = null;

        $chunks = $this->splitChunks($binaryMidi);
        $headerChunk = array_shift($chunks);

        $this->parseHeaderChunk($headerChunk);

        if ($this->header->trackCount !== count($chunks)) {
            $this->log('Track count in header "' . $this->header->trackCount . '" does not match real track count "' . count($chunks) . '"');
        }

        $this->parseTrackChunks($chunks);
    }

    public function splitChunks($binaryMidi) {
        $chunks = array();

        while (strlen($binaryMidi) > 0) {
            if (strlen($binaryMidi) < 8) {
                $this->log('Incomplete chunk header (expected 8 bytes - got ' . strlen($binaryMidi) . ' bytes)');
                break;
            }

            $signature = substr($binaryMidi, 0, 4);
            $expectedSignature = count($chunks) == 0 ? 'MThd' : 'MTrk';

            if ($signature !== $expectedSignature) {
                $this->log('Invalid chunk signature "' . $signature . '" (expected "' . $expectedSignature . '")');
                break;
            }

            $chunkLength = unpack('Nlength', substr($binaryMidi, 4, 4));
            $chunkLength = $chunkLength['length'];

            if (strlen($binaryMidi) - 8 < $chunkLength) {
                $this->log('Incomplete chunk (expected ' . $chunkLength . ' bytes - got ' . (strlen($binaryMidi) - 8) . ' bytes)');
                break;
            }

            $chunks[] = substr($binaryMidi, 8, $chunkLength);
            $binaryMidi = substr($binaryMidi, 8 + $chunkLength);
        }

        return $chunks;
    }

    private function parseHeaderChunk($headerChunk) {
        $this->header = (object) unpack('ntype/ntrackCount/ntimeDivision', $headerChunk);

        if (!in_array($this->header->type, array(0, 1, 2))) {
            $this->log('Invalid MIDI format type ("' . $this->header->type . '")');
        }
    }

    private function parseTrackChunks($chunks) {
        $this->tracks = array();

        foreach ($chunks as $chunk) {
            //$this->tracks[] = '';
        }
    }

    private function log($message) {
        echo $message . PHP_EOL;
    }
}
