<?php
/**
 * Circles - bring cloud-users closer
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


use OC\User\NoUserException;
use OCA\Circles\Db\CirclesMapper;
use OCA\Circles\Db\MembersMapper;
use OCA\Circles\Exceptions\CircleTypeNotValid;
use OCA\Circles\Exceptions\GroupDoesNotExistException;
use OCA\Circles\Exceptions\MemberAlreadyExistsException;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\Circle;
use \OCA\Circles\Model\Member;
use OCP\IL10N;
use OCP\IUserManager;

class MembersService {

	/** @var string */
	private $userId;

	/** @var IL10N */
	private $l10n;

	/** @var IUserManager */
	private $userManager;

	/** @var ConfigService */
	private $configService;

	/** @var CirclesMapper */
	private $dbCircles;

	/** @var MembersMapper */
	private $dbMembers;

	/** @var EventsService */
	private $eventsService;

	/** @var MiscService */
	private $miscService;

	public function __construct(
		$userId,
		IL10N $l10n,
		IUserManager $userManager,
		ConfigService $configService,
		DatabaseService $databaseService,
		EventsService $eventsService,
		MiscService $miscService
	) {
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->configService = $configService;
		$this->eventsService = $eventsService;
		$this->miscService = $miscService;

		$this->dbCircles = $databaseService->getCirclesMapper();
		$this->dbMembers = $databaseService->getMembersMapper();
	}


	/**
	 * @param $circleId
	 * @param $name
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function addMember($circleId, $name) {

		try {
			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
			$this->dbMembers->getMemberFromCircle($circleId, $this->userId)
							->hasToBeModerator();
		} catch (\Exception $e) {
			throw $e;
		}

		try {
			$member = $this->getFreshNewMember($circleId, $name);
		} catch (\Exception $e) {
			throw $e;
		}
		$member->inviteToCircle($circle->getType());
		$this->dbMembers->editMember($member);

		$this->eventsService->onMemberNew($circle, $member);

		return $this->dbMembers->getMembersFromCircle($circleId, $circle->getViewer());
	}


	/**
	 * @param int $circleId
	 * @param string $groupId
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function importMembersFromGroup($circleId, $groupId) {

		try {
			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
			$this->dbMembers->getMemberFromCircle($circleId, $this->userId)
							->hasToBeModerator();
		} catch (\Exception $e) {
			throw $e;
		}

		$group = \OC::$server->getGroupManager()
							 ->get($groupId);
		if ($group === null) {
			throw new GroupDoesNotExistException('This group does not exist');
		}

		foreach ($group->getUsers() as $user) {
			try {
				$member = $this->getFreshNewMember($circleId, $user->getUID());

				$member->inviteToCircle($circle->getType());
				$this->dbMembers->editMember($member);

				$this->eventsService->onMemberNew($circle, $member);
			} catch (MemberAlreadyExistsException $e) {
			} catch (\Exception $e) {
				throw $e;
			}
		}

		return $this->dbMembers->getMembersFromCircle($circleId, $circle->getViewer());
	}


	/**
	 * getMember();
	 *
	 * Will return any data of a user related to a circle (as a Member). User can be a 'non-member'
	 *
	 * @param $circleId
	 * @param $userId
	 *
	 * @return Member
	 * @throws \Exception
	 */
	public function getMember($circleId, $userId) {

		try {
			$this->dbMembers->getMemberFromCircle($circleId, $this->userId)
							->hasToBeMember();
			$member = $this->dbMembers->getMemberFromCircle($circleId, $userId);
		} catch (\Exception $e) {
			throw $e;
		}

		return $member;
	}


	/**
	 * Check if a fresh member can be generated (by addMember)
	 *
	 * @param $circleId
	 * @param $name
	 *
	 * @return null|Member
	 * @throws MemberAlreadyExistsException
	 * @throws NoUserException
	 */
	private function getFreshNewMember($circleId, $name) {

		if (!$this->userManager->userExists($name)) {
			throw new NoUserException($this->l10n->t("This user does not exist"));
		}

		try {
			$member = $this->dbMembers->getMemberFromCircle($circleId, $name);

		} catch (MemberDoesNotExistException $e) {
			$member = new Member($this->l10n, $name, $circleId);
			$this->dbMembers->add($member);
		}

		if ($this->memberAlreadyExist($member)) {
			throw new MemberAlreadyExistsException(
				$this->l10n->t('This user is already a member of the circle')
			);
		}

		return $member;
	}


	/**
	 * return if member already exists
	 *
	 * @param Member $member
	 *
	 * @return bool
	 */
	private function memberAlreadyExist($member) {
		return ($member->getLevel() > Member::LEVEL_NONE
				|| ($member->getStatus() !== Member::STATUS_NONMEMBER
					&& $member->getStatus() !== Member::STATUS_REQUEST)
		);
	}


	/**
	 * @param int $circleId
	 * @param string $name
	 * @param int $level
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function levelMember($circleId, $name, $level) {

		$level = (int)$level;
		try {
			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
			if ($circle->getType() === Circle::CIRCLES_PERSONAL) {
				throw new CircleTypeNotValid(
					$this->l10n->t('You cannot edit level in a personal circle')
				);
			}

			$member = $this->dbMembers->getMemberFromCircle($circle->getId(), $name);
			if ($member->getLevel() !== $level) {
				if ($level === Member::LEVEL_OWNER) {
					$this->switchOwner($circle, $member);
				} else {
					$this->editMemberLevel($circle, $member, $level);
				}

				$this->eventsService->onMemberLevel($circle, $member);
			}

			return $this->dbMembers->getMembersFromCircle($circleId, $circle->getViewer());
		} catch (\Exception $e) {
			throw $e;
		}

	}


	/**
	 * @param Circle $circle
	 * @param Member $member
	 * @param $level
	 *
	 * @throws \Exception
	 */
	private function editMemberLevel(Circle $circle, Member &$member, $level) {
		try {
			$isMod = $this->dbMembers->getMemberFromCircle($circle->getId(), $this->userId);
			$isMod->hasToBeModerator();
			$isMod->hasToBeHigherLevel($level);

			$member->hasToBeMember();
			$member->cantBeOwner();
			$isMod->hasToBeHigherLevel($member->getLevel());

			$member->setLevel($level);
			$this->dbMembers->editMember($member);
		} catch (\Exception $e) {
			throw $e;
		}

	}

	/**
	 * @param Circle $circle
	 * @param Member $member
	 *
	 * @throws \Exception
	 */
	public function switchOwner(Circle $circle, Member &$member) {
		try {
			$isMod = $this->dbMembers->getMemberFromCircle($circle->getId(), $this->userId);
			$isMod->hasToBeOwner();

			$member->cantBeOwner();
			$member->setLevel(Member::LEVEL_OWNER);
			$this->dbMembers->editMember($member);

			$isMod->setLevel(Member::LEVEL_ADMIN);
			$this->dbMembers->editMember($isMod);

		} catch (\Exception $e) {
			throw $e;
		}
	}


	/**
	 * @param $circleId
	 * @param $name
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function removeMember($circleId, $name) {

		try {
			$isMod = $this->dbMembers->getMemberFromCircle($circleId, $this->userId);
			$isMod->hasToBeModerator();

			$member = $this->dbMembers->getMemberFromCircle($circleId, $name);
			$member->cantBeOwner();

			$isMod->hasToBeHigherLevel($member->getLevel());
		} catch (\Exception $e) {
			throw $e;
		}

		$member->setStatus(Member::STATUS_NONMEMBER);
		$member->setLevel(Member::LEVEL_NONE);
		$this->dbMembers->editMember($member);

		$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
		$this->eventsService->onMemberLeaving($circle, $member);

		return $this->dbMembers->getMembersFromCircle($circleId, $circle->getViewer());
	}


	/**
	 * When a user is removed, remove him from all Circles
	 *
	 * @param $userId
	 */
	public function onUserRemoved($userId) {
		$this->dbMembers->removeAllFromUserId($userId);
	}


}