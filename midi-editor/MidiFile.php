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

        $this->header = $this->parseHeaderChunk($headerChunk);

        if ($this->header->trackCount !== count($chunks)) {
            $this->log('Track count in header "' . $this->header->trackCount . '" does not match real track count "' . count($chunks) . '"');
        }

        $this->tracks = array();

        foreach ($chunks as $chunk) {
            $this->tracks[] = $this->parseTrackChunk($chunk);
        }
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
        $header = (object) unpack('ntype/ntrackCount/ntimeDivision', $headerChunk);

        if (!in_array($header->type, array(0, 1, 2))) {
            $this->log('Invalid MIDI format type ("' . $header->type . '")');
        }

        return $header;
    }

    private function parseTrackChunk($chunk) {
        $track = array();

        //while (strlen($chunk) > 0) {
        //    $track[] = ...;
        //}

        return $track;
    }

    private function log($message) {
        echo $message . PHP_EOL;
    }
}
