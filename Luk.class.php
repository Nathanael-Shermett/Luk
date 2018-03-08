<?php

/**
 * Contains methods that assist in calculations where LUK plays a prominent role. As this is a utility class, these
 * methods are primarily helper functions.
 *
 * @author Nathanael Shermett <nathanael@shermett.me>
 * @license MIT
 */
class Luk
{
	/**
	 * Picks one or more random entries out of an array.
	 *
	 * NOTE: Each array entry must be an array itself with a "value" key corresponding to the array value, and a
	 * "luckiness" key corresponding to its relative weight. For example, if you are attempting to pick a number
	 * between 1-6 out of an array, the array should be formatted like so:
	 *
	 * [
	 *   ['value' => 1, 'luckiness' => 1],
	 *   ['value' => 2, 'luckiness' => 2],
	 *   ['value' => 3, 'luckiness' => 3],
	 *   ['value' => 4, 'luckiness' => 4],
	 *   ['value' => 5, 'luckiness' => 5],
	 *   ['value' => 6, 'luckiness' => 6],
	 * ]
	 *
	 * Bear in mind that while selected values are ranked based on their luckiness, the *amount* of luckiness is
	 * inconsequential. Therefore, if in the above example the value "6" had a luckiness value of "10000", it would be
	 * just as likely to be selected as if it had a luckiness of "6", but both would be more likely to be selected than
	 * the value with a luckiness of "5".
	 *
	 * @access public
	 * @param int   $luck
	 * @param array $array
	 * @param int   $num
	 * @return array|int If $num == 1, then the key of the randomly-selected array value is chosen. If $num > 1, then
	 *                   an array of keys (length $num) is returned.
	 */
	public static function array_rand($luk, $array, $num = 1)
	{
		// Our result array.
		$result = [];

		// In order to avoid an infinite loop down the line, we must ensure $num is not bigger than our array itself.
		if ($num > count($array))
		{
			$num = count($array);
		}

		// Sort the array by LUK (descending).
		self::sort_by_luckiness($array);

		// What are the base odds (as a decimal) of each array item being randomly selected? (e.g .166666...)
		//
		// NOTE: We first divide 100 by the number of items to get a base percentage, and then divide THAT
		// by 100 to convert the percentage into a decimal value.
		$odds = 100 / count($array) / 100;

		// Our actual odds, given $luk.
		$odds = self::get_odds($luk, $odds);

		// We need to retrieve $num random keys, so we're going to repeat the following logic until that
		// criterion is satisfied.
		while (count($result) < $num)
		{
			// Loop through the array (which is sorted by luckiness DESC).
			foreach ($array as $key => $entry)
			{
				// Based on our calculated odds, should we return this array element?
				if (self::roll_odds($odds))
				{
					// We should add this array element to our result.
					$result[] = $key;

					// Now that we've added it to our result, let's remove it from $array so it's not selected more
					// than once.
					unset($array[ $key ]);

					// Stop looking through the array (we need to check if $num has been satisfied).
					break;
				}
				else
				{
					continue;
				}
			}
		}

		return ($num === 1) ? $result[0] : $result;
	}

	/**
	 * Our LUK multiplier. Given a LUK level, the corresponding LUK multiplier is returned.
	 *
	 * NOTE: Both X and Y values less than 1 are resolved to 1.
	 *
	 * The formula can be tweaked here (update the URL if you do):
	 * https://www.wolframalpha.com/input/?i=log+fit+%7B%7B200,+10.0%7D,+%7B100,+7.0%7D,+%7B50,+4.0%7D,+%7B20,+2.0%7D%7D
	 *
	 * @access public
	 * @param float $x LUK level.
	 * @return float LUK multiplier.
	 */
	public static function get_multiplier($x)
	{
		// If $x is below 1, set it to 1.
		$x = ($x < 1) ? 1 : $x;

		// The formula.
		$y = 2.92573 * log(0.0726566 * $x);

		// Is the result greater than 1?
		if ($y > 1)
		{
			return $y;
		}
		else
		{
			return 1;
		}
	}

	/**
	 * Given a LUK level and starting odds, what are the resultant odds (as a decimal, e.g .1666666) of something
	 * occurring?
	 *
	 * NOTE: The resultant odds cannot go above $cap. Therefore, if $cap is .9 (default), the odds of [whatever]
	 * occurring will be no higher than 90%.
	 *
	 * @access public
	 * @param int   $luk
	 * @param float $base_odds
	 * @param float $cap
	 * @return float
	 */
	public static function get_odds($luk, $base_odds, $cap = .9)
	{
		$result = $base_odds * self::get_multiplier($luk);

		return ($result <= $cap) ? $result : $cap;
	}

	/**
	 * Same as PHP rand(), but a LUK level is factored in. Therefore, higher numbers are more likely to result than
	 * lower numbers.
	 *
	 * NOTE: This is inefficient alpha-foxtrot.
	 *
	 * @access public
	 * @param int $luk
	 * @param int $min
	 * @param int $max Defaults to 50, not getrandmax(), for performance reasons.
	 * @return int
	 */
	public static function rand($luk, $min = 0, $max = 50)
	{
		// Uncomment the following line only if you like slow apps.
		// $max = getrandmax();

		// The highest luckiness value in our range.
		$luckiness = $max;

		// Range array.
		$array = array_reverse(range($min, $max));
		foreach ($array as $key => $value)
		{
			$array[ $key ]['value'] = $value;
			$array[ $key ]['luckiness'] = $luckiness--;
		}

		return self::array_rand($luk, $array)['value'];
	}

	/**
	 * Sorts a multi-dimensional array by its children's "luckiness" key. Result is sorted in descending order by
	 * default.
	 *
	 * @access private
	 * @param array  $array Passed by reference.
	 * @param string $sort  The direction to sort; either DESC or ASC.
	 * @return array
	 */
	private static function sort_by_luckiness(&$array, $sort = 'DESC')
	{
		usort($array, function($a, $b) use ($sort)
		{
			if ($a['luckiness'] == $b['luckiness'])
			{
				// $a and $b have the same luckiness, so don't sort them.
				return 0;
			}
			elseif ($a['luckiness'] > $b['luckiness'])
			{
				// $a has more luckiness, so we move $b down the array (if $sort == 'DESC').
				return ($sort == 'DESC') ? -1 : 1;
			}
			else
			{
				// $a has less luckiness, so we move $b up the array (if $sort == 'DESC').
				return ($sort == 'DESC') ? 1 : -1;
			}
		});

		return $array;
	}

	/**
	 * Takes a percentage (as a decimal, i.e .16666), and returns TRUE $percentage percent of the time.
	 *
	 * NOTE: LUK does not play any role in the outcome of this method, though it can be useful in the creation of other
	 * functions that utilize LUK (@see self::array_rand())
	 *
	 * NOTE 2: $percentage is rounded to two decimal places.
	 *
	 * @access public
	 * @param float $percentage
	 * @return bool
	 */
	public static function roll_odds($percentage)
	{
		// Round the percentage to 5 (this is totally arbitrary) decimal places.
		// We'll also do the same for a percentage of 100%, as we'll need this for our calculation shortly.
		$percentage = (float)number_format($percentage, 5);
		$percentage_100 = (float)number_format(1.0, 5);

		// Get rid of the decimal, effectively turning our floats into integers.
		$threshold = $percentage * 100000;
		$max = $percentage_100 * 100000;

		// Generate a random number between 0 and $max. If the randomly-generated number falls within our threshold,
		// then we can return TRUE, otherwise FALSE.
		return (rand(0, $max) < $threshold) ? TRUE : FALSE;
	}
}