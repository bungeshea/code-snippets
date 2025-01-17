<?php

namespace Code_Snippets;

/**
 * Empty class to better support interoperability between core and pro.
 *
 * @package Code_Snippets
 */
class Licensing {

	/**
	 * Determine whether the current site has an active license.
	 *
	 * @return bool
	 */
	public function is_licensed(): bool {
		return false;
	}
}
