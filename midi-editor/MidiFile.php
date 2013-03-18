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

    public function save($filename) {
        file_put_contents($filename, print_r($this->tracks, true));
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
                $chunkLength = strlen($binaryMidi) - 8;
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
        $trackEvents = array();
        $offset = 0;

        while ($offset < strlen($chunk)) {
            $deltaTime = $this->parseVariableLengthValue($chunk, $offset);
            $eventType = $this->parseByte($chunk, $offset);

            $trackEvent = (object) array(
                'deltaTime' => $deltaTime,
                'eventType' => $eventType,
            );

            if ($eventType == 0xff) {
                $trackEvent->metaEventType = $this->parseByte($chunk, $offset);
                $metaEventLength = $this->parseVariableLengthValue($chunk, $offset);
                $trackEvent->metaEventData = substr($chunk, $offset, $metaEventLength);
                $offset += $metaEventLength;
            } else {
                $trackEvent->channel = $eventType & 0x0f;
                $eventType = ($eventType & 0xf0) >> 4;

                if ($eventType == 0x8) {
                    $trackEvent->eventType = 'noteOff';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->velocity = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0x9) {
                    $trackEvent->eventType = 'noteOn';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->velocity = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xa) {
                    $trackEvent->eventType = 'noteAftertouch';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->aftertouch = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xb) {
                    $trackEvent->eventType = 'controller';
                    $trackEvent->controller = $this->parseByte($chunk, $offset);
                    $trackEvent->value = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xc) {
                    $trackEvent->eventType = 'programChange';
                    $trackEvent->program = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xd) {
                    $trackEvent->eventType = 'channelAftertouch';
                    $trackEvent->aftertouch = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xe) {
                    $trackEvent->eventType = 'pitchBend';
                    $value1 = $this->parseByte($chunk, $offset);
                    $value2 = $this->parseByte($chunk, $offset);
                    $trackEvent->pitch = ($value2 << 8) | $value1;
                } else {
                    $this->log('Ignored unknown event type "' . $eventType . '"');
                }
            }

            $trackEvents[] = $trackEvent;
        }

        return $trackEvents;
    }

    private function parseVariableLengthValue($chunk, &$offset) {
        $value = 0;
        $valueLength = 0;

        while ($valueLength < 4) {
            $byte = $this->parseByte($chunk, $offset);

            $valueLength++;
            $byteFollows = $byte & 0x80 ? true : false;
            $byte = $byte & 0x7f;
            $value = ($value << 7) | $byte;

            if (!$byteFollows) {
                break;
            }
        }

        return $value;
    }

    private function parseByte($chunk, &$offset) {
        if ($offset >= strlen($chunk)) {
            $this->log('Unexpected end of chunk');
            return 0;
        }

        $byte = substr($chunk, $offset, 1);
        $byte = unpack('Cbyte', $byte);
        $byte = $byte['byte'];

        $offset += 1;

        return $byte;
    }

    private function log($message) {
        echo $message . PHP_EOL;
    }
}
