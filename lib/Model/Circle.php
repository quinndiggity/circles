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

namespace OCA\Circles\Model;

use Exception;
use OCA\Circles\Exceptions\CircleTypeNotValid;
use OCA\Circles\Exceptions\FederatedCircleNotAllowedException;

class Circle extends BaseCircle implements \JsonSerializable {

	/** @var bool */
	private $fullJson = false;

	/** @var bool */
	private $lightJson = false;


	public function getTypeString() {
		switch ($this->getType()) {
			case self::CIRCLES_PERSONAL:
				return 'Personal';
			case self::CIRCLES_HIDDEN:
				return 'Hidden';
			case self::CIRCLES_PRIVATE:
				return 'Private';
			case self::CIRCLES_PUBLIC:
				return 'Public';
			case self::CIRCLES_ALL:
				return 'All';
		}

		return 'none';
	}

	public function getTypeLongString() {
		return self::typeLongString($this->getType());
	}


	public function getInfo() {
		return $this->getTypeLongString();
	}


	public function jsonSerialize() {
		$json = array(
			'id'             => $this->getId(),
			'name'           => $this->getName(),
			'owner'          => $this->getOwner(),
			'user'           => $this->getViewer(),
			'description'    => $this->getDescription(),
			'settings'       => $this->getSettings(),
			'type'           => $this->getTypeString(),
			'creation'       => $this->getCreation(),
			'typeString'     => $this->getTypeString(),
			'typeLongString' => $this->getTypeLongString(),
			'unique_id'      => $this->getUniqueId($this->fullJson),
			'members'        => $this->getMembers(),
			'groups'         => $this->getGroups(),
			'links'          => $this->getLinks()
		);

		if ($this->lightJson) {
			$json['members'] = [];
			$json['links'] = [];
			$json['groups'] = [];
		}

		return $json;
	}


	public function getJson($full = false, $light = false) {
		$this->fullJson = $full;
		$this->lightJson = $light;
		$json = json_encode($this);
		$this->fullJson = false;
		$this->lightJson = false;

		return $json;
	}



//	/**
//	 * set all infos from an Array.
//	 *
//	 * @param $arr
//	 *
//	 * @return $this
//	 */
//	public function fromArray($arr) {
//		$this->setId($arr['id']);
//		$this->setName($arr['name']);
//		$this->setUniqueId($arr['unique_id']);
//		$this->setDescription($arr['description']);
//		$this->setType($arr['type']);
//		$this->setCreation($arr['creation']);
////		$this->setOwnerMemberFromArray($arr);
////		$this->setUserMemberFromArray($arr);
//
//		return $this;
//	}

	/**
	 * set all infos from an Array.
	 *
	 * @param $l10n
	 * @param $arr
	 *
	 * @return $this
	 */
	public static function fromArray($l10n, $arr) {
		$circle = new Circle($l10n);

		$circle->setId($arr['id']);
		$circle->setName($arr['name']);
		$circle->setUniqueId($arr['unique_id']);
		$circle->setDescription($arr['description']);
		if (key_exists('links', $arr)) {
			$circle->setLinks($arr['links']);
		}
		if (key_exists('settings', $arr)) {
			$circle->setSettings($arr['settings']);
		}
		$circle->setType($arr['type']);
		$circle->setCreation($arr['creation']);

		if (key_exists('user', $arr)) {
			$circle->setViewer(Member::fromArray($l10n, $arr['user']));
		}
		if (key_exists('owner', $arr)) {
			$circle->setOwner(Member::fromArray($l10n, $arr['owner']));
		}

		return $circle;
	}


	public static function fromJSON($l10n, $json) {
		return self::fromArray($l10n, json_decode($json, true));
	}

//
//
//	/**
//	 * set User Infos from Array
//	 *
//	 * @param $array
//	 */
//	// TODO rewrite the function based of setOwnerMemberFromArray()
//	private function setUserMemberFromArray($array) {
//		if (key_exists('status', $array)
//			&& key_exists('level', $array)
//			&& key_exists('joined', $array)
//		) {
//			$user = new Member($this->l10n);
//			$user->setStatus($array['status']);
//			$user->setLevel($array['level']);
//			$user->setJoined($array['joined']);
//			$this->setUser($user);
//		}
//	}


	/**
	 * @throws CircleTypeNotValid
	 */
	public function cantBePersonal() {
		if ($this->getType() === self::CIRCLES_PERSONAL) {
			throw new CircleTypeNotValid(
				$this->l10n->t("This option is not available for personal circles")
			);
		}
	}


	/**
	 * @throws FederatedCircleNotAllowedException
	 */
	public function hasToBeFederated() {
		if ($this->getSetting('allow_links') !== 'true') {
			throw new FederatedCircleNotAllowedException(
				$this->l10n->t('The circle is not Federated')
			);
		}
	}

	/**
	 * @param $type
	 *
	 * @return string
	 */
	public static function typeLongString($type) {
		switch ($type) {
			case self::CIRCLES_PERSONAL:
				return 'Personal circle';
			case self::CIRCLES_HIDDEN:
				return 'Hidden circle';
			case self::CIRCLES_PRIVATE:
				return 'Private circle';
			case self::CIRCLES_PUBLIC:
				return 'Public circle';
			case self::CIRCLES_ALL:
				return 'All circles';
		}

		return 'none';
	}


}


