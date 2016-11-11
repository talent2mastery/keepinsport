<?php

namespace Ks\ClubBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * ClubHasUsersRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ClubHasUsersRepository extends EntityRepository
{
    /**
     *
     * @return boolean Si l'utilisateur est djà dans le club
     */
    public function isInClub(\Ks\ClubBundle\Entity\Club $club, \Ks\UserBundle\Entity\User $user)
    {      
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder->select('count(chu)')
                ->from($this->getEntityName(), 'chu')
                ->where('chu.club = ?1')
                ->andWhere('chu.user = ?2')
                ->andWhere('chu.membershipAskInProgress = ?3')
                ->setParameter(1, $club->getId())
                ->setParameter(2, $user->getId())
                ->setParameter(3, FALSE);
        
        $query = $queryBuilder->getQuery();
        return (boolean)$query->getSingleScalarResult();
    }
    
        /**
     *
     * @return boolean Si l'utilisateur est djà dans le club
     */
    public function askForMembershipIsInProgress(\Ks\ClubBundle\Entity\Club $club, \Ks\UserBundle\Entity\User $user)
    {      
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder->select('count(chu)')
                ->from($this->getEntityName(), 'chu')
                ->where('chu.club = ?1')
                ->andWhere('chu.user = ?2')
                ->andWhere('chu.membershipAskInProgress = ?3')
                ->setParameter(1, $club->getId())
                ->setParameter(2, $user->getId())
                ->setParameter(3, TRUE);
        
        $query = $queryBuilder->getQuery();
        return (boolean)$query->getSingleScalarResult();
    }
    
    public function deleteUserFromClub(\Ks\ClubBundle\Entity\ClubHasUsers $clubHasUsers) {
        $this->_em->remove($clubHasUsers);
        $this->_em->flush();
    }
    
    public function askForMembership(\Ks\ClubBundle\Entity\Club $club, \Ks\UserBundle\Entity\User $user) {
        $clubHasUsers = new \Ks\ClubBundle\Entity\ClubHasUsers( $club, $user );
        $clubHasUsers->setMembershipAskInProgress( true );
        
        $this->_em->persist($clubHasUsers);
        $this->_em->flush();
    }
    
    public function removeAskForMembership(\Ks\ClubBundle\Entity\ClubHasUsers $clubHasUsers) {        
        $this->_em->remove($clubHasUsers);
        $this->_em->flush();
    }
    
    public function acceptAnAskForMembershipInProgress(\Ks\ClubBundle\Entity\ClubHasUsers $clubHasUsers) {        
        $clubHasUsers->setMembershipAskInProgress( false );
        
        $this->_em->persist($clubHasUsers);
        $this->_em->flush();
    }
    
    public function refuseAnAskForMembershipInProgress(\Ks\ClubBundle\Entity\ClubHasUsers $clubHasUsers) {        
        $this->_em->remove($clubHasUsers);
        $this->_em->flush();
    }
}