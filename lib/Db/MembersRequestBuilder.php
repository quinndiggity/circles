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

namespace OCA\Circles\Db;


use OCP\DB\QueryBuilder\IQueryBuilder;
use Doctrine\DBAL\Query\QueryBuilder;

class MembersRequestBuilder extends CoreRequestBuilder {


//	/**
//	 * Limit the request to a minimum member level.
//	 *
//	 * @param IQueryBuilder $qb
//	 * @param integer $level
//	 */
//	protected function limitToMemberLevel(IQueryBuilder & $qb, $level) {
//		$pf = ($qb->getType() === QueryBuilder::SELECT) ? $this->default_select_alias . '.' : '';
//		$qb->andWhere(
//			$qb->expr()
//			   ->gte($pf . 'level', $qb->createNamedParameter($level))
//		);
//	}


	/**
	 * @return IQueryBuilder
	 */
	protected function getGroupsSelectSql() {
		$qb = $this->dbConnection->getQueryBuilder();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->select('g.circle_id', 'g.group_id', 'g.level', 'g.note', 'g.joined')
		   ->from(CoreRequestBuilder::TABLE_GROUPS, 'g');
		$this->default_select_alias = 'g';

		return $qb;
	}


	/**
	 * Base of the Sql Insert request for Shares
	 *
	 * @return IQueryBuilder
	 */
	protected function getGroupsInsertSql() {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(CoreRequestBuilder::TABLE_GROUPS)
		   ->setValue('joined', $qb->createFunction('NOW()'));

		return $qb;
	}


	/**
	 * Base of the Sql Insert request for Shares
	 *
	 * @param int $circleId
	 * @param string $groupId
	 *
	 * @return IQueryBuilder
	 */
	protected function getGroupsUpdateSql($circleId, $groupId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$expr = $qb->expr();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->update(CoreRequestBuilder::TABLE_GROUPS)
		   ->where(
			   $expr->andX(
				   $expr->eq('circle_id', $qb->createNamedParameter($circleId)),
				   $expr->eq('group_id', $qb->createNamedParameter($groupId))
			   )
		   );

		return $qb;
	}


	/**
	 * Base of the Sql Insert request for Shares
	 *
	 * @param string $groupId
	 *
	 * @return IQueryBuilder
	 */
	protected function getGroupsDeleteSql($groupId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$expr = $qb->expr();

		$qb->delete(CoreRequestBuilder::TABLE_GROUPS)
		   ->where($expr->eq('group_id', $qb->createNamedParameter($groupId)));

		return $qb;
	}


}