<?php

class SideBySide {
    const AFFIXES_CLI = array("\e[1m\e[101m\e[97m", "\e[0m", "\e[1m\e[42m\e[97m", "\e[0m");
    const AFFIXES_HTML = array('<del>', '</del>', '<ins>', '</ins>');
    const AFFIXES_MD = array('~~', '~~', '**', '**');

    const _DIRECTION_TOP_LEFT = 0;
    const _DIRECTION_TOP = 1;
    const _DIRECTION_LEFT = 2;

    const _DATA_MAX = 'max';
    const _DATA_DIRECTION = 'direction';
    const _DATA_STREAK = 'streak';

    private $streak = 5;
    private $affixes = self::AFFIXES_CLI;

    /**
     * @param int $streak
     * @throws Exception
     */
    public function setStreak($streak) {
        $newStreak = (int) $streak;
        if ($newStreak !== $streak || $newStreak < 0) {
            throw new Exception('Streak should be a non-negative integer');
        }
        $this->streak = $streak;
    }

    /**
     * @param string[] $affixes
     * @throws Exception
     */
    public function setAffixes($affixes) {
        if (!is_array($affixes) || count($affixes) !== 4) {
            throw new Exception('Affixes should be an array with four entries');
        }
        foreach ($affixes as $affix) {
            if (gettype($affix) !== 'string') {
                throw new Exception('All entries of affixes must be strings');
            }
        }
        $this->affixes = $affixes;
    }

    /**
     * @param string $text
     * @return string[]
     */
    private static function split($text) {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param string[] $source
     * @param string[] $target
     * @return int[]
     */
    private static function getCommonAffixLengths(&$source, &$target) {
        $cs = count($source);
        $ct = count($target);

        $prefix = 0;
        while ($prefix < $cs && $prefix < $ct && $source[$prefix] === $target[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while ($prefix + $suffix < $cs && $prefix + $suffix < $ct
            && $source[$cs - $suffix - 1] === $target[$ct - $suffix - 1]) {
            $suffix++;
        }

        return array($prefix, $suffix);
    }

    /**
     * @param string[] $chars
     * @param bool[] $map
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    private static function format(&$chars, &$map, $prefix, $suffix) {
        $result = array();
        $currentMarked = false;
        $cnt = count($chars);
        for ($i = 0; $i < $cnt; $i++) {
            if ($map[$i] !== $currentMarked) {
                $result[] = $map[$i] ? $prefix : $suffix;
                $currentMarked = $map[$i];
            }
            $result[] = $chars[$i];
        }
        if ($currentMarked) {
            $result[] = $suffix;
        }
        return join('', $result);
    }

    /**
     * @param array[][] $matrix
     * @param int $sourceLevel
     * @param int $targetLevel
     * @param int $sourceShift
     * @param int $targetShift
     * @return array
     */
    private static function getMatrixValue(&$matrix, $sourceLevel, $targetLevel, $sourceShift, $targetShift) {
        $row = $targetLevel - $targetShift;
        $col = $sourceLevel - $sourceShift;
        return $row < 0 || $col < 0 ? array(self::_DATA_MAX => 0, self::_DATA_STREAK => 0) : $matrix[$row][$col];
    }

    /**
     * @param array[][] $matrix
     * @param int $sourceLevel
     * @param int $sourceShift
     * @param string[] $sourceChars
     * @param int $targetLevel
     * @param int $targetShift
     * @param string[] $targetChars
     * @return array
     */
    private static function computeMatrixValue(&$matrix, $sourceLevel, $sourceShift, &$sourceChars,
                                               $targetLevel, $targetShift, &$targetChars) {
        $left = self::getMatrixValue($matrix, $sourceLevel - 1, $targetLevel, $sourceShift, $targetShift);
        $top = self::getMatrixValue($matrix, $sourceLevel, $targetLevel - 1, $sourceShift, $targetShift);
        $topLeft = $sourceChars[$sourceLevel] === $targetChars[$targetLevel]
            ? self::getMatrixValue($matrix, $sourceLevel - 1, $targetLevel - 1, $sourceShift, $targetShift)
            : array(self::_DATA_MAX => -1);
        if ($left[self::_DATA_MAX] >= $top[self::_DATA_MAX] && $left[self::_DATA_MAX] > $topLeft[self::_DATA_MAX]) {
            $left[self::_DATA_DIRECTION] = self::_DIRECTION_LEFT;
            $left[self::_DATA_STREAK] = 0;
            return $left;
        }
        if ($top[self::_DATA_MAX] > $topLeft[self::_DATA_MAX]) {
            $top[self::_DATA_DIRECTION] = self::_DIRECTION_TOP;
            $top[self::_DATA_STREAK] = 0;
            return $top;
        }
        $topLeft[self::_DATA_DIRECTION] = self::_DIRECTION_TOP_LEFT;
        $topLeft[self::_DATA_MAX]++;
        $topLeft[self::_DATA_STREAK]++;
        return $topLeft;
    }

    /**
     * @param array[][] $matrix
     * @param int $sourceLevel
     * @param int $sourceShift
     * @param bool[] $sourceMap
     * @param int $targetLevel
     * @param int $targetShift
     * @param bool[] $targetMap
     */
    private function updateMaps(&$matrix, $sourceLevel, $sourceShift, &$sourceMap,
                                $targetLevel, $targetShift, &$targetMap) {
        while (true) {
            $row = $targetLevel - $targetShift;
            $col = $sourceLevel - $sourceShift;
            if ($row < 0 && $col < 0) {
                return;
            }
            if ($row < 0 || $col >= 0 && $matrix[$row][$col][self::_DATA_DIRECTION] !== self::_DIRECTION_TOP) {
                $sourceMap[$sourceLevel] =
                    $row < 0 || $matrix[$row][$col][self::_DATA_DIRECTION] === self::_DIRECTION_LEFT;
                $sourceLevel--;
            }
            if ($col < 0 || $row >= 0 && $matrix[$row][$col][self::_DATA_DIRECTION] !== self::_DIRECTION_LEFT) {
                $targetMap[$targetLevel] =
                    $col < 0 || $matrix[$row][$col][self::_DATA_DIRECTION] === self::_DIRECTION_TOP;
                $targetLevel--;
            }
        }
    }

    /**
     * @param array[][] $matrix
     * @param int $sourceLevel
     * @param int $sourceFrom
     * @param int $sourceTo
     * @param string[] $sourceChars
     * @param bool[] $sourceMap
     * @param int $targetLevel
     * @param int $targetFrom
     * @param int $targetTo
     * @param string[] $targetChars
     * @param bool[] $targetMap
     * @return boolean
     */
    private function computeMapsProcess(&$matrix, $sourceLevel, &$sourceFrom, $sourceTo, &$sourceChars, &$sourceMap,
                                        $targetLevel, &$targetFrom, $targetTo, &$targetChars, &$targetMap) {
        $matrixValue = self::computeMatrixValue($matrix, $sourceLevel, $sourceFrom, $sourceChars,
            $targetLevel, $targetFrom, $targetChars);
        $matrix[$targetLevel - $targetFrom][] = $matrixValue;
        if ($this->streak > 0 && $matrixValue[self::_DATA_STREAK] === $this->streak) {
            $this->updateMaps($matrix, $sourceLevel, $sourceFrom, $sourceMap, $targetLevel, $targetFrom, $targetMap);
            $sourceFrom = $sourceLevel;
            $targetFrom = $targetLevel;
            while ($sourceLevel < $sourceTo && $targetLevel < $targetTo
                && $sourceChars[$sourceLevel] === $targetChars[$targetLevel]) {
                $sourceMap[$sourceLevel] = false;
                $sourceLevel++;
                $targetMap[$targetLevel] = false;
                $targetLevel++;
            }
            $sourceFrom = $sourceLevel;
            $targetFrom = $targetLevel;
            return true;
        }
        return false;
    }

    /**
     * @param string[] $source
     * @param int $sourceFrom
     * @param int $sourceTo
     * @param bool[] $sourceMap
     * @param string[] $target
     * @param int $targetFrom
     * @param int $targetTo
     * @param bool[] $targetMap
     */
    private function computeMapsIteration(&$source, &$sourceFrom, $sourceTo, &$sourceMap,
                                          &$target, &$targetFrom, $targetTo, &$targetMap) {
        $matrix = array();
        $sourceLevel = $sourceFrom;
        $targetLevel = $targetFrom;
        while (true) {
            if ($sourceLevel < $sourceTo) {
                for ($i = $targetFrom; $i < $targetLevel; $i++) {
                    if ($this->computeMapsProcess($matrix, $sourceLevel, $sourceFrom, $sourceTo, $source, $sourceMap,
                        $i, $targetFrom, $targetTo, $target, $targetMap)) {
                        return;
                    }
                }
                $sourceLevel++;
            }
            if ($targetLevel < $targetTo) {
                $matrix[] = array();
                for ($i = $sourceFrom; $i < $sourceLevel; $i++) {
                    if ($this->computeMapsProcess($matrix, $i, $sourceFrom, $sourceTo, $source, $sourceMap,
                        $targetLevel, $targetFrom, $targetTo, $target, $targetMap)) {
                        return;
                    }
                }
                $targetLevel++;
            }
            if ($sourceLevel === $sourceTo && $targetLevel === $targetTo) {
                $this->updateMaps($matrix, $sourceLevel - 1, $sourceFrom, $sourceMap,
                    $targetLevel - 1, $targetFrom, $targetMap);
                $sourceFrom = $sourceLevel;
                $targetFrom = $targetLevel;
                return;
            }
        }
    }

    /**
     * @param string[] $source
     * @param string[] $target
     * @param bool[] $sourceMap
     * @param bool[] $targetMap
     * @param int $prefix
     * @param int $suffix
     */
    private function computeMaps(&$source, &$target, &$sourceMap, &$targetMap, $prefix, $suffix) {
        $sourceFrom = $targetFrom = $prefix;
        $sourceTo = count($source) - $suffix;
        $targetTo = count($target) - $suffix;
        while (true) {
            $this->computeMapsIteration($source, $sourceFrom, $sourceTo, $sourceMap,
                $target, $targetFrom, $targetTo, $targetMap);
            if ($sourceFrom === $sourceTo) {
                for ($i = $targetFrom; $i < $targetTo; $i++) {
                    $targetMap[$i] = true;
                }
                return;
            }
            if ($targetFrom === $targetTo) {
                for ($i = $sourceFrom; $i < $sourceTo; $i++) {
                    $sourceMap[$i] = true;
                }
                return;
            }
        }
    }

    /**
     * @param string $source
     * @param string $target
     * @throws Exception
     * @return string[]
     */
    public function compute($source, $target) {
        if (gettype($source) !== 'string' || gettype($target) !== 'string') {
            throw new Exception('Source and target must be strings');
        }

        $sourceChars = self::split($source);
        $sourceMap = array_fill(0, count($sourceChars), false);

        $targetChars = self::split($target);
        $targetMap = array_fill(0, count($targetChars), false);

        list($prefixLength, $suffixLength) = self::getCommonAffixLengths($sourceChars, $targetChars);
        $this->computeMaps($sourceChars, $targetChars, $sourceMap, $targetMap, $prefixLength, $suffixLength);

        return array(
            self::format($sourceChars, $sourceMap, $this->affixes[0], $this->affixes[1]),
            self::format($targetChars, $targetMap, $this->affixes[2], $this->affixes[3])
        );
    }
}