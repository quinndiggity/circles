<?php

/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Circles\Service;


use OCA\Circles\Db\CirclesMapper;
use OCA\Circles\Db\MembersMapper;

class DatabaseService {

	private $circlesMapper;
	private $membersMapper;

	public function __construct($circlesMapper, $membersMapper) {
		$this->circlesMapper = $circlesMapper;
		$this->membersMapper = $membersMapper;
	}

	/**
	 * @return CirclesMapper
	 */
	public function getCirclesMapper() {
		return $this->circlesMapper;
	}

	/**
	 * @return MembersMapper
	 */
	public function getMembersMapper() {
		return $this->membersMapper;
	}

}