<?php

namespace Ks\EvolutionBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * EvolutionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EvolutionRepository extends EntityRepository
{
    /**
     *
     * @return query builder des évolutions triées par nombre de votes et libellé, pas utilisée !
     */
//    public function findEvolution() {      
//        $queryBuilder = $this->_em->createQueryBuilder();
// 
//        $queryBuilder->select('s')
//                ->from('KsEvolutionBundle:Evolution', 's')
//                ->orderBy('s.likes', 'ASC');
//        return $queryBuilder;
//    }
    
    /**
     * 
     * @param \Ks\EvolutionBundle\Entity\Evolution $
     * @param \Ks\UserBundle\Entity\User $user
     */
    public function voteOnEvolution(\Ks\EvolutionBundle\Entity\Evolution $evolution, \Ks\UserBundle\Entity\User $user)
    {
        $evolutionHasVotes = new EvolutionHasVotes($evolution, $user);

        $evolutionHasVotes->setVoter($user);
        $evolutionHasVotes->setEvolution($evolution);
        
        $this->_em->persist($evolutionHasVotes);
        $this->_em->flush();
    }
    
    /**
     *
     * @param \Ks\EvolutionBundle\Entity\EvolutionHasVotes $HasVotes 
     */
    public function removeVoteOnEvolution(\Ks\EvolutionBundle\Entity\EvolutionHasVotes $evolutionHasVotes)
    {      
        $this->_em->remove($evolutionHasVotes);
        $this->_em->flush();
    }
    
    /**
     *
     * @return boolean Si l'utilisateur a déjà voté sur l'évolution
     */
    public function haveAlreadyVoted(\Ks\EvolutionBundle\Entity\Evolution $evolution, \Ks\UserBundle\Entity\User $user)
    {      
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder->select('count(a.id)')
                ->from('KsEvolutionBundle:Evolution', 'a')
                ->innerJoin('a.voters', 'v')
                ->where('v.evolution = ?1')
                ->andWhere('v.voter = ?2')
                ->setParameter(1, $evolution->getId())
                ->setParameter(2, $user->getId());
        
        $query = $queryBuilder->getQuery();
        return (boolean)$query->getSingleScalarResult();
    }
    
    public function getNumVotesOnEvolution(\Ks\EvolutionBundle\Entity\Evolution $evolution)
    {
        $dbh    = $this->_em->getConnection();
        $stmt   = $dbh->executeQuery(
            'select count(*) from ks_evolution_has_votes where evolution_id = ? group by evolution_id',
            array($evolution->getId())
        );
        
        return $stmt->fetchColumn();
    }
    
    public function getEvolutionsWithUserHasVotedAndNumVotes(\Ks\UserBundle\Entity\User $user)
    {
        $dbh        = $this->_em->getConnection();
        $stmt = $dbh->executeQuery(
            'select id, descriptionShort, description, releaseDate, creationDate, '
            .'       (select count(1) from ks_evolution_has_votes where evolution_id = t.id) as numVotes, '
            .'       (select 1 from ks_evolution_has_votes where evolution_id = t.id and voter_id = ?) as userHasVoted '
            .' from ks_evolution t '
            .' order by numVotes desc',
            array($user->getId())
        );
       
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getNumVotesByUser(\Ks\UserBundle\Entity\User $user)
    {
        $dbh    = $this->_em->getConnection();
        $stmt   = $dbh->executeQuery(
            'select count(*) from ks_evolution_has_votes where voter_id = ? group by voter_id',
            array($user->getId())
        );
        
        return $stmt->fetchColumn();
    }
 
    /**
     *
     * @param type $numArticles
     * @return type 
     */
    public function getLinksToBestVotedEvolutions($nbEvolutions)
    {
        $dbh            = $this->_em->getConnection();
        $nbEvolutions    = (int)$nbEvolutions;
        $sql            = "select"
            ." a.id, a.descriptionShort, "
            ." (select count(1) from ks_evolution_has_votes where evolution_id = a.id) as numVotes "
            ." from ks_evolution a"
            ." where 1"
            ." order by numVotes desc"
            ." limit $nbEvolutions";
        
        $stmt = $dbh->executeQuery($sql);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}