# Side by Side

This is a PHP class for computing and formatting a character-based diff between two UTF-8 strings to facilitate side-by-side comparison.


## Usage

```php
include 'SideBySide.php';
$diff = new SideBySide();
$diff->setAffixes(array('{', '}', '(', ')'));
print_r($diff->compute('single file', 'side-by-side'));
```

Output:

```
Array
(
    [0] => si{ngl}e{ f}i{l}e
    [1] => si(d)e(-by-s)i(d)e
)
```


## Efficiency

The code implements a modified version of the [LCS algorithm](https://en.wikipedia.org/wiki/Longest_common_subsequence_problem#Solution_for_two_sequences). It works fastest on texts with relatively small localized edits. It runs roughly in time comparable to *(number of edits)* × *(size of edit)*<sup>2</sup>. With `streak` equal to `0`, the run time is always proportional to *(size of the first text)* × *(size of the second text)*.


## API


### `$diff = new SideBySide()`

The constructor does not accept any arguments.


### `$diff.setAffixes($affixes)`

Accepts an array of size 4: the prefix and suffix for formatting the source; then the prefix and suffix for formatting the target. See the sample usage above.

Predefined values:
- `SideBySide.AFFIXES_CLI` (default): CLI [escape sequences](https://en.wikipedia.org/wiki/ANSI_escape_code) for highlighting the removed text with red background and the new text with green background.
- `SideBySide.AFFIXES_HTML`: `<del>` and `<ins>` tags respectively.
- `SideBySide.AFFIXES_MD`: `~~` and `**`.


### `$diff.setStreak($streak)`

Accepts a single non-negative integer. If 0, the code will run the original LCS (with an additional optimization of chopping off common prefix and suffix of the two strings). If the value is positive, it defines the number of common characters considered to be enough to abandon the LCS part and match the strings linearly.

Smaller positive values lead to potentially faster but less accurate computation. Larger values approach 0 in their effect. The default value for `streak` is 5.