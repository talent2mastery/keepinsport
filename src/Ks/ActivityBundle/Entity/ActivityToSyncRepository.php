<?php

namespace Ks\ActivityBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * ActivityToSyncRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ActivityToSyncRepository extends EntityRepository
{
    /**
     * Pour supprimer la table temporaire des activités à synchroniser
     */
    public function deleteActivityToSync($userId, $serviceId)
    {
        $dbh    = $this->_em->getConnection();
        $stmt   = $dbh->executeQuery(
            'delete from ks_activity_to_sync where user_id = ? and service_id = ?',
            array($userId, $serviceId)
        );
        $this->_em->flush();
    }
}