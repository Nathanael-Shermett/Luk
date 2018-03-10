<?php

/**
 * Contains methods that assist in calculations where LUK plays a prominent role. As this is a utility class, these
 * methods are primarily helper functions.
 *
 * @author  Nathanael Shermett <nathanael@shermett.me>
 * @license MIT
 */
class Luk
{
	/**
	 * Our LUK formula. Given a LUK level ($x), the corresponding LUK multiplier ($y) is returned.
	 *
	 * NOTE: Both X and Y values less than 1 are resolved to 1.
	 *
	 * @access private
	 * @param int $x LUK level.
	 * @return float LUK multiplier.
	 */
	private static function __formula($x)
	{
		// If $x is below 1, set it to 1.
		$x = ($x < 1) ? 1 : $x;

		// The formula.
		$y = 1.5 * exp(.15 * $x);

		// Return $y.
		//
		// If $y is below 1, set it to 1.
		return ($y > 1) ? $y : 1;
	}

	/**
	 * Custom implementation of PHP's array_rand() function. Picks one or more random entries out of an array given a
	 * LUK level. If an array element has a 'weight' key/value, it is also weighted accordingly.
	 *
	 * LUCKINESS:
	 * Each array element *MUST* be an array itself with a minimum of a "luckiness" key whose value corresponds
	 * with how "good" the element is.
	 *
	 * For example, if you are attempting to flip a coin (and heads is better), the array should be formatted something
	 * like this:
	 *
	 * [
	 *   ['result' => 'heads', 'luckiness' => 1],
	 *   ['result' => 'tails', 'luckiness' => 2],
	 * ]
	 *
	 * Bear in mind that while selected values are ranked based on their luckiness, the *amount* of luckiness is
	 * inconsequential. Therefore, in the above example, if "heads" had a luckiness value of 10000, it would be just as
	 * likely to be selected as if it had a luckiness of 2, but both would be more likely to be selected than "tails"
	 * whose luckiness is only 1. In other words, "luckiness" is not a weight, but rather a way to tell
	 * Luk::array_rand() which values to favor over others.
	 *
	 * WEIGHT:
	 * Each array element *CAN* have a "weight" key, but it is not required. If no weight is present, "1" is implied.
	 *
	 * For example, if you are attempting to roll a weighted dice (where a roll of "1" is less lucky, but also FIVE
	 * TIMES more likely to be rolled than any other individual number), the array should be formatted something like
	 * this:
	 *
	 * [
	 *   ['value' => 1, 'luckiness' => 1, 'weight' => 5],
	 *   ['value' => 2, 'luckiness' => 2],
	 *   ['value' => 3, 'luckiness' => 3],
	 *   ['value' => 4, 'luckiness' => 4],
	 *   ['value' => 5, 'luckiness' => 5],
	 *   ['value' => 6, 'luckiness' => 6],
	 * ]
	 *
	 * @access public
	 * @param int   $luck
	 * @param array $array
	 * @param int   $num
	 * @return array|int If $num == 1, then the key of the randomly-selected array value is chosen. If $num > 1, then
	 *                   an array of keys (length $num) is returned.
	 * @see    self::array_fetch_weighted()
	 * @see    self::calc_weights()
	 */
	public static function array_rand($luk, $array, $num = 1)
	{
		// Given $luk, calculate the weights for each of our array elements.
		//
		// NOTE: If weights are already present in $array, the new weights are a product of the LUK multiplier and
		// starting weights.
		self::calc_weights($luk, $array);

		// Simplify the array so that self::array_fetch_weighted() can work with it.
		// We need it formatted like so: [key => weight, key => weight]
		foreach ($array as $key => $element)
		{
			$array[$key] = $element['weight'];
		}

		// Given our calculated weights and $num, return the result.
		return self::array_fetch_weighted($array, $num);
	}

	/**
	 * Weighted implementation of PHP's array_rand() function. Returns a single element (key) from an array.
	 *
	 * EXAMPLE: Given an array like so: ['A' => 2, 'B' => 1]
	 * "A" would be returned approximately 66.6% of the time, and "B" approximately 33.3% of the time.
	 *
	 * NOTE: This method does not factor in LUK, and should not be confused with self::array_rand().
	 *
	 * @access private
	 * @param array $array Formatted like so: [0 => 2, 1 => 1] (each element's value is its weight)
	 * @param int   $num   The number of elements to return.
	 * @return array|int  If $num == 1, then the key of the randomly-selected array value is chosen. If $num > 1, then
	 *                     an array of keys (length $num) is returned.
	 * @see    self::array_rand()
	 */
	private static function array_fetch_weighted($array, $num = 1)
	{
		// Our result array.
		$result = [];

		// In order to avoid an infinite loop down the line, we must ensure $num is not bigger than our array itself.
		if ($num > count($array))
		{
			$num = count($array);
		}

		// Calculate the sum of our array's weights. We'll use this shortly.
		$total_weight = array_sum($array);

		// We need to retrieve $num random keys, so we're going to repeat the following logic until that
		// criterion is satisfied.
		while (count($result) < $num)
		{
			// Returns a random number between 1 and the sum of the array's weights.
			$rand = rand(1, (int)$total_weight);

			// Check each element in $array in order to see if we should return it. Loop stops as soon as a
			// result is chosen.
			foreach ($array as $key => $weight_value)
			{
				// Subtract this element's weight value from $rand.
				$rand -= $weight_value;

				// If the previous subtraction results in a number less than/equal to zero, we have found our result.
				//
				// To understand how this works, consider the following scenario:
				//
				// $array = [
				//   0 => 2,
				//   1 => 1,
				//   2 => 1,
				// ];
				//
				// Because $rand's max value was the sum of the weights, in this case it would be anywhere from 1 to 4.
				// Therefore, as we're looping through $array (starting from the top):
				//
				// ITERATION ONE:
				//   $rand equals 1, 2, 3, or 4
				//   $weight_value equals 2
				//
				//   if $rand equals 1 AND $weight_value == 2, then 1 - 2 == -1 (return key 0)
				//   if $rand equals 2 AND $weight_value == 2, then 2 - 2 ==  0 (return key 0)
				//   if $rand equals 3 AND $weight_value == 2, then 3 - 2 ==  1 (no return)
				//   if $rand equals 4 AND $weight_value == 2, then 4 - 2 ==  2 (no return)
				//
				// ITERATION TWO:
				//   $rand equals 1 or 2 (this is because $rand is a running subtotal from the previous iteration)
				//   $weight_value equals 1
				//
				//   if $rand equals 1 AND $weight_value == 1, then 1 - 1 ==  0 (return key 1)
				//   if $rand equals 2 AND $weight_value == 1, then 2 - 1 ==  1 (no return)
				//
				// ITERATION THREE:
				//   $rand equals 1
				//   $weight_value equals 1
				//
				//   if $rand equals 1 AND $weight_value == 1, then 1 - 1 ==  0 (return key 2)
				//
				// </end_verbose_commment>
				if ($rand <= 0)
				{
					// Add this array element to our result.
					$result[] = $key;

					// Now that we've added it to our result, let's remove it from our $array so it's not selected more
					// than once (in the event $num is greater than 1).
					unset($array[$key]);

					// Stop looking through the array.
					break;
				}
				else
				{
					// Try to add the next array element.
					continue;
				}
			}
		}

		return ($num === 1) ? $result[0] : $result;
	}

	/**
	 * Given the LUK level, calculate the weights for each value in an array.
	 *
	 * To ensure the luckiest options are weighted more heavily, the input array is first sorted (in descending order)
	 * by each array element's "luckiness". Then, starting with the most lucky value, we begin walking through the
	 * array, like so:
	 *
	 * 1. The first element's weight is equal to self::get_multiplier(LUK)
	 * 2. The second element's weight is equal to self::get_multiplier(LUK / 10)
	 * 3. The third element's weight is equal to self::get_multiplier(LUK / 10 / 10)
	 * 4. Et cetera.
	 *
	 * NOTE 1: If the array element already has a weight, it is multiplied by the new calculated weight. This allows
	 * for
	 * default weights to still be influenced by LUK whilst maintaining their original weight's integrity.
	 *
	 * NOTE 2: Weights are multiplied by 1000 to maintain some level of decimal integrity despite "weight" having an
	 * integer value.
	 *
	 * @access private
	 * @param int   $luk
	 * @param array $array The input array (passed by reference). Should be multidimensional (with at least a
	 *                     "luckiness" key/value, and optionally a "weight" key/value).
	 * @return array The input array, but with a 'weight' key (and corresponding value) appended to each array element.
	 * @see    self::sort_by_luckiness()
	 */
	private static function calc_weights($luk, &$array)
	{
		$luk_running = $luk;

		// Sort the array by luckiness (descending).
		self::sort_by_luckiness($array);

		// Apply a weight to each array element.
		foreach ($array as $key => $element)
		{
			// Get our LUK multiplier.
			$multiplier = self::get_multiplier($luk_running);

			// If this element does not already have a weight, give it a default weight of 1.
			if (!isset($element['weight']))
			{
				$array[$key]['weight'] = 1;
			}

			// Apply the weight. It is multiplied by 1000 to allow for some decimal precision despite the fact PHP's
			// random functions only work with integers.
			//
			// NOTE: Due the the *= operator this new weight is multiplied by the array element's starting weight (if
			// applicable) so that the weight's integrity is maintained despite the influence of LUK.
			$array[$key]['weight'] *= (int)($multiplier * 1000);

			// Are there more array elements after this one?
			//
			// NOTE: next() moves the array pointer fowards.
			if (next($array))
			{
				// Is the next array element less lucky than this one?
				//
				// NOTE: Due to the previous call of next(), current() techncially refers to the next element in the loop.
				if (current($array)['luckiness'] < $element['luckiness'])
				{
					// The next element is less lucky, so let's decrement the LUK for that iteration.
					$luk_running -= ($luk / 10);
				}
			}
		}

		// Return the resultant array.
		return $array;
	}

	/**
	 * Alias of self::__formula()
	 *
	 * @access private
	 * @param int $luk LUK level.
	 * @return float LUK multiplier.
	 * @see    self::__formula()
	 */
	public static function get_multiplier($luk)
	{
		$x = $luk;

		return self::__formula($x);
	}

	/**
	 * Same as PHP's rand() function, but a LUK level is factored in. Therefore, higher numbers are more likely to
	 * result than lower numbers.
	 *
	 * NOTE: This is inefficient alpha-foxtrot.
	 *
	 * @access public
	 * @param int $luk
	 * @param int $min
	 * @param int $max Defaults to 100, not getrandmax(), for performance reasons.
	 * @return int
	 * @see    self::array_rand()
	 */
	public static function rand($luk, $min = 0, $max = 100)
	{
		// Uncomment the following line if you like server timeouts.
		// $max = getrandmax();

		// The highest luckiness value in our range.
		$luckiness = $max;

		// Range array.
		$array = array_reverse(range($min, $max));
		foreach ($array as $key => $value)
		{
			$array[$key]['value'] = $value;
			$array[$key]['luckiness'] = $luckiness--;
		}

		return self::array_rand($luk, $array)['value'];
	}

	/**
	 * Custom implementation of PHP's shuffle() function. Behaves the same way, but keys are not reset.
	 *
	 * @access private
	 * @param array $array Associaive array to be shuffled.
	 * @return TRUE
	 */
	private static function shuffle_assoc(&$array)
	{
		// The array's keys (as an array).
		$keys = array_keys($array);

		// Shuffle the keys.
		shuffle($keys);

		// Create a $new array using the shuffled keys' order as a blueprint.
		foreach ($keys as $key)
		{
			$new[$key] = $array[$key];
		}

		// Rebuild $array.
		$array = $new;

		// Return TRUE no matter what.
		return TRUE;
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
		// Shuffle the array before sorting. We do this because in the event that multiple array elements have the
		// same "luckiness", we don't want the array order to skew their results over time.
		self::shuffle_assoc($array);

		// Sort by 'luckiness'.
		uasort($array, function($a, $b) use ($sort)
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
}