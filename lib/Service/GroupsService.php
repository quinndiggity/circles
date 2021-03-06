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
use OCA\Circles\Db\MembersRequest;
use OCA\Circles\Exceptions\CircleTypeNotValid;
use OCA\Circles\Exceptions\GroupCannotBeOwnerException;
use OCA\Circles\Exceptions\GroupDoesNotExistException;
use OCA\Circles\Exceptions\MemberAlreadyExistsException;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\Circle;
use \OCA\Circles\Model\Member;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;

class GroupsService {

	/** @var string */
	private $userId;

	/** @var IL10N */
	private $l10n;

	/** @var IGroupManager */
	private $groupManager;

	/** @var MembersRequest */
	private $membersRequest;
	/** @var CirclesMapper */
	private $dbCircles;

	/** @var MembersMapper */
	private $dbMembers;

	/** @var MiscService */
	private $miscService;

	/**
	 * GroupsService constructor.
	 *
	 * @param string $userId
	 * @param IL10N $l10n
	 * @param IGroupManager $groupManager
	 * @param DatabaseService $databaseService ,
	 * @param MembersRequest $membersRequest
	 * @param MiscService $miscService
	 */
	public function __construct(
		$userId, IL10N $l10n, IGroupManager $groupManager, DatabaseService $databaseService,
		MembersRequest $membersRequest, MiscService $miscService
	) {
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->membersRequest = $membersRequest;
		$this->miscService = $miscService;

		$this->dbCircles = $databaseService->getCirclesMapper();
		$this->dbMembers = $databaseService->getMembersMapper();
	}


	/**
	 * @param $circleId
	 * @param $groupId
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function linkGroup($circleId, $groupId) {

		try {
			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
			$this->dbMembers->getMemberFromCircle($circleId, $this->userId)
							->hasToBeAdmin();

			$group = $this->getFreshNewMember($circleId, $groupId);
		} catch (\Exception $e) {
			throw $e;
		}

		$group->setLevel(Member::LEVEL_MEMBER);
		$this->membersRequest->editGroup($group);

//		$this->eventsService->onMemberNew($circle, $group);
		return $this->membersRequest->getGroups($circleId, $circle->getViewer());
	}


	/**
	 * Check if a fresh member can be generated (by linkGroup)
	 *
	 * @param $circleId
	 * @param $groupId
	 *
	 * @return null|Member
	 * @throws MemberAlreadyExistsException
	 * @throws GroupDoesNotExistException
	 */
	private function getFreshNewMember($circleId, $groupId) {

		if (!$this->groupManager->groupExists($groupId)) {
			throw new GroupDoesNotExistException($this->l10n->t("This group does not exist"));
		}

		try {
			$member = $this->membersRequest->forceGetGroup($circleId, $groupId);
		} catch (MemberDoesNotExistException $e) {
			$member = new Member($this->l10n, '', $circleId);
			$member->setGroupId($groupId);
			$this->membersRequest->insertGroup($member);
		}

		if ($member->getLevel() > Member::LEVEL_NONE) {
			throw new MemberAlreadyExistsException(
				$this->l10n->t('This group is already linked to the circle')
			);
		}

		return $member;
	}


	/**
	 * @param int $circleId
	 * @param string $groupId
	 * @param int $level
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function levelGroup($circleId, $groupId, $level) {

		$level = (int)$level;
		try {
			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);
			if ($circle->getType() === Circle::CIRCLES_PERSONAL) {
				throw new CircleTypeNotValid(
					$this->l10n->t('You cannot edit level in a personal circle')
				);
			}

			$group = $this->membersRequest->forceGetGroup($circle->getId(), $groupId);
			if ($group->getLevel() !== $level) {
				if ($level === Member::LEVEL_OWNER) {
					throw new GroupCannotBeOwnerException(
						$this->l10n->t('Group cannot be set as owner of a circle')
					);
				} else {
					$this->editGroupLevel($circle, $group, $level);
				}

//				$this->eventsService->onMemberLevel($circle, $member);
			}

			return $this->membersRequest->getGroups($circle->getId(), $circle->getViewer());
		} catch (\Exception $e) {
			throw $e;
		}

	}


	/**
	 * @param Circle $circle
	 * @param Member $group
	 * @param $level
	 *
	 * @throws \Exception
	 */
	private function editGroupLevel(Circle $circle, Member &$group, $level) {
		try {
			$isMod = $this->dbMembers->getMemberFromCircle($circle->getId(), $this->userId);
			$isMod->hasToBeAdmin();
			$isMod->hasToBeHigherLevel($level);

			$group->hasToBeMember();
			$group->cantBeOwner();
			$isMod->hasToBeHigherLevel($group->getLevel());

			$group->setLevel($level);
			$this->membersRequest->editGroup($group);
		} catch (\Exception $e) {
			throw $e;
		}
	}


	/**
	 * @param int $circleId
	 * @param string $groupId
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function unlinkGroup($circleId, $groupId) {
		try {
			$isMod = $this->dbMembers->getMemberFromCircle($circleId, $this->userId);
			$isMod->hasToBeAdmin();

			$group = $this->membersRequest->forceGetGroup($circleId, $groupId);
			$group->cantBeOwner();
			$isMod->hasToBeHigherLevel($group->getLevel());

			$group->setLevel(Member::LEVEL_NONE);
			$this->membersRequest->editGroup($group);

			$circle = $this->dbCircles->getDetailsFromCircle($circleId, $this->userId);

			//		$this->eventsService->onMemberLeaving($circle, $member);

		} catch (\Exception $e) {
			throw $e;
		}

		return $this->membersRequest->getGroups($circle->getId(), $circle->getViewer());
	}


	/**
	 * When a group is removed, remove it from all Circles
	 *
	 * @param string $groupId
	 */
	public function onGroupRemoved($groupId) {
		$this->membersRequest->unlinkAllFromGroupId($groupId);
	}


}