<?php

namespace Ks\ActivityBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * ActivitySessionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ActivitySessionRepository extends EntityRepository
{
    public function findUserActivitiesSessionsByWeek(\Ks\UserBundle\Entity\User $user, $weekNumber)
    {
        $year = date("Y");

        $startDate = date("Y-m-d H:i:s", strtotime("{$year}-W{$weekNumber}-1")); //Returns the date of monday in week
        $endDate = date("Y-m-d H:i:s", strtotime("{$year}-W{$weekNumber}-7"));

        $qb = $this->_em->createQueryBuilder();
        $qb ->select('a')
            ->from('KsActivityBundle:ActivitySession', 'a')
            ->where('a.user = ?1')
            ->andWhere('a.issuedAt > ?2')
            ->andWhere('a.issuedAt < ?3')
            ->andWhere('a.isDisabled != TRUE')
            ->andWhere('a.isValidate != FALSE')
            ->setParameter(1, $user->getId())
            ->setParameter(2, $startDate)
            ->setParameter(3, $endDate);
        
        $query = $qb->getQuery();

        return $query->getResult();
    }
    
    public function findUserActivitiesSessionsSinceDate(\Ks\UserBundle\Entity\User $user, \DateTime $beginDate)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb ->select('a')
            ->from('KsActivityBundle:ActivitySession', 'a')
            ->where('a.user = ?1')
            ->andWhere('a.issuedAt > ?2')
            ->andWhere('a.isDisabled != TRUE')
            ->andWhere('a.isValidate != FALSE')
            ->setParameter(1, $user->getId())
            ->setParameter(2, $beginDate);
        
        $query = $qb->getQuery();

        return $query->getResult();
    }
    
    public function sumOfTheEarnedPoints($activitiesSessions) {
        $sum = 0;
        
        foreach( $activitiesSessions as $activitySession ) {      
            foreach( $activitySession->getPoints() as $activitySessionEarnPoints ) {
                if(! $activitySessionEarnPoints->getActivitySession()->getIsDisabled() && $activitySessionEarnPoints->getActivitySession()->getIsValidate()) {
                    $sum += $activitySessionEarnPoints->getPoints();
                }
            }
        }
        
        return $sum;
    }
    
    function getDateRange(&$start, &$end, $date) {
        $seconds_in_day = 86400;
        $day_of_week = date("w", $date); 
        $start = $date - ($day_of_week * $seconds_in_day);
        $end = $date + ((6 - $day_of_week) * $seconds_in_day);
    }
    
    public function findLastActivitiesSession(\Ks\UserBundle\Entity\User $user)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb ->select('a')
            ->from('KsActivityBundle:ActivitySession', 'a')
            ->where('a.user = ?1')
            ->andWhere('a.isValidate = ?2')
            ->setParameter(1, $user->getId())
            ->setParameter(2, true)
            ->orderBy('a.issuedAt', 'desc')
            ->setMaxResults(5);
        
        $query = $qb->getQuery();

        return $query->getResult();
    }
    
    public function findNotConnectedToEvent(\Ks\UserBundle\Entity\User $user)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb ->select('a')
            ->from('KsActivityBundle:ActivitySession', 'a')
            ->leftJoin('a.event', 'e')
            ->where('a.user = ?1')
            ->andWhere('e.id is NULL')
            ->setParameter(1, $user->getId())
            ->orderBy('a.issuedAt', 'desc');
        
        $query = $qb->getQuery();

        return $query->getResult();
    }
    
     public function findNotConnectedToEventQB(\Ks\UserBundle\Entity\User $user)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb ->select('a')
            ->from('KsActivityBundle:ActivitySession', 'a')
            ->leftJoin('a.event', 'e')
            ->where('a.user = ?1')
            ->andWhere('e.id is NULL')
            ->setParameter(1, $user->getId())
            ->orderBy('a.issuedAt', 'desc');
        
        return $qb;
    }

    
    /**
     * 
     * @param type $firstDayOfMonth
     * @param type $lastDayOfMonth
     */
    public function findCompetitionsAndTrainingsInfos($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId = null, $equipmentId = null, $planId = null) {
        $dbh        = $this->_em->getConnection();
        $sql = 'select a.id, a.wasOfficial, ((substring(a.duration,1,2) * 3600) + (substring(a.duration,4,2) * 60) + substring(a.duration,7,2)) as durationInSeconds '
            .' FROM ks_activity a'
            .' WHERE a.user_id = :userId'
            .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
            .' AND SUBSTRING(a.issuedAt, 1, 10) <= :endOn'
            .' AND a.duration is not null'
            .' AND a.isDisabled = FALSE'
            .' AND a.isValidate != FALSE';
        ;
        
        $vars = array();
        $vars["userId"] = $userId;
        $vars["startOn"] = $firstDayOfPeriod;
        $vars["endOn"] = $lastDayOfPeriod;
        
        if (isset($sportId) && $sportId != null) {
            $sql .= ' AND a.sport_id = :sportId';
            $vars['sportId']   = $sportId;
        }
        
        if (isset($equipmentId) && $equipmentId != null) {
            $sql .= ' AND a.id in (select activitysession_id from ks_equipment_used_in_activity where equipment_id = :equipmentId) ';
            $vars['equipmentId']   = $equipmentId;
        }
        
        if (isset($planId) && $planId != null) {
            $sql .= ' AND a.id in (select e.activitySession_id from ks_event e where e.coachingPlan_id = :planId)'; //in pour pas faire planter un select = avec plusieurs lignes et avec e.id = a.event_id pour utiliser la FK pour les perf
            $vars['planId']   = $planId;
        }
        
        $stmt = $dbh->prepare($sql);
        
        $stmt->execute($vars);
        
        $infos = array(
            "competition"   => array(
                "number"    => 0,
                "duration"  => 0
            ),
            "training"      => array(
                "number"    => 0,
                "duration"  => 0
            ),
        );
        while ($activity = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if( $activity["wasOfficial"] == true ) {
                $infos["competition"]["number"] += 1;
                $infos["competition"]["duration"] += (int)$activity["durationInSeconds"];
            } else {
                $infos["training"]["number"] += 1;
                $infos["training"]["duration"] += (int)$activity["durationInSeconds"];
            }
            //var_dump($activity["durationInSeconds"]);
            
        }
        
        return $infos;
    }
    
    public function findCompetitionsAndTrainingsRate($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId = null, $equipmentId = null, $planId = null) {
        $infos = $this->findCompetitionsAndTrainingsInfos($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId, $equipmentId, $planId);
        
        $totalActivities = $infos["competition"]["number"] + $infos["training"]["number"];
        
        $competitionsAndTrainingsNumberRate = array(
            "competition" => $infos["competition"]["number"],
            "training"    => $infos["training"]["number"],
        );
        
        $totalDuration = $infos["competition"]["duration"] + $infos["training"]["duration"];
        
        $competitionsAndTrainingsMinutesRate = array(
            "competition" => $infos["competition"]["duration"],
            "training"    => $infos["training"]["duration"],
        );
        
        return array(
            "number"    => $competitionsAndTrainingsNumberRate,
            "minutes"   => $competitionsAndTrainingsMinutesRate
        );
    }
    
    public function findActivitiesDuration($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId = null, $equipmentId = null, $planId = null) {
        $infos = $this->findCompetitionsAndTrainingsInfos($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId, $equipmentId, $planId);
        
        return $infos["competition"]["duration"] + $infos["training"]["duration"];
    }
    
    public function findTeamSportSessionsWithResult($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId = null, $equipmentId = null, $planId =null) {
        $dbh        = $this->_em->getConnection();
        
        $sqlParts   = array(
            'select' => 'SELECT'
                .' r.code, COUNT(a.id) as nb ',
            'from'  => 'FROM ks_activity a'
                .' INNER JOIN ks_activity_result r on (r.id = a.result_id)',
            'where' => 'WHERE a.user_id = :userId'
                .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
                .' AND SUBSTRING(a.issuedAt, 1, 10) <= :endOn'
                .' AND a.isDisabled = FALSE'
                .' AND a.isValidate != FALSE',
            'group' => 'GROUP BY r.id',
            'order' => '',
            'limit' => ''
        );
        
        $vars = array();
        $vars["startOn"] = $firstDayOfPeriod;
        $vars["endOn"] = $lastDayOfPeriod;
        $vars["userId"] = $userId;
        
        if (isset($sportId) && $sportId != null) {
            $sqlParts['where'] .= ' AND a.sport_id = :sportId';
            $vars['sportId']   = $sportId;
        }
        
        if (isset($equipmentId) && $equipmentId != null) {
            $sqlParts['where'] .= ' AND a.id in (select activitysession_id from ks_equipment_used_in_activity where equipment_id = :equipmentId) ';
            $vars['equipmentId']   = $equipmentId;
        }
        
        if (isset($planId) && $planId != null) {
            $sqlParts['where'] .= ' AND a.id in (select e.activitySession_id from ks_event e where e.coachingPlan_id = :planId)'; //in pour pas faire planter un select = avec plusieurs lignes et avec e.id = a.event_id pour utiliser la FK pour les perf
            $vars['planId']   = $planId;
        }
        
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);
        
        $results = array(
            "v" => 0,
            "n" => 0,
            "d" => 0
        );
        while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[$result["code"]] = (int)$result["nb"];
        }
        
        return $results;
    }
    
    public function findEnduranceSessionDetails($userId, $firstDayOfPeriod, $lastDayOfPeriod, $sportId = null, $equipmentId = null, $planId =null) {
        $dbh        = $this->_em->getConnection();
        
        $sqlParts   = array(
            'select' => 'SELECT'
                .' a.distance , a.elevationGain, a.elevationLost, a.trackingDatas',
            'from'  => 'FROM ks_activity a',
            'where' => 'WHERE a.user_id = :userId'
                .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
                .' AND SUBSTRING(a.issuedAt, 1, 10) <= :endOn'
                .' AND a.type != \'article\''
                .' AND a.isDisabled = FALSE'
                .' AND a.isValidate != FALSE',
            'group' => '',
            'order' => '',
            'limit' => ''
        );
        
        $vars = array();
        $vars["startOn"] = $firstDayOfPeriod;
        $vars["endOn"] = $lastDayOfPeriod;
        $vars["userId"] = $userId;
        
        if (isset($sportId) && $sportId != null) {
            $sqlParts['where'] .= ' AND a.sport_id = :sportId';
            $vars['sportId']   = $sportId;
        }
        
        if (isset($equipmentId) && $equipmentId != null) {
            $sqlParts['where'] .= ' AND a.id in (select activitysession_id from ks_equipment_used_in_activity where equipment_id = :equipmentId) ';
            $vars['equipmentId']   = $equipmentId;
        }
        
        if (isset($planId) && $planId != null) {
            $sqlParts['where'] .= ' AND a.id in (select e.activitySession_id from ks_event e where e.coachingPlan_id = :planId)'; //in pour pas faire planter un select = avec plusieurs lignes et avec e.id = a.event_id pour utiliser la FK pour les perf
            $vars['planId']   = $planId;
        }
        
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);
        
        $details = array(
            "distance" => 0,
            "d+" => 0,
            "d-" => 0
        );
        while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if( $result["trackingDatas"] != null ) {
                $trackingDatas = json_decode($result["trackingDatas"], true);
                if( isset( $trackingDatas["info"]["distance"] ) && is_null((float)$result["distance"])) {
                    $details["distance"] += (float)$trackingDatas["info"]["distance"];
                }
                if( isset( $trackingDatas["info"]["D+"] ) && is_null((float)$result["elevationGain"])) {
                    $details["d+"] += (int)$trackingDatas["info"]["D+"];
                }
                if( isset( $trackingDatas["info"]["D-"] ) && is_null((float)$result["elevationLost"])) {
                    $details["d-"] += (int)$trackingDatas["info"]["D-"];
                }
            }
            if (!is_null((float)$result["distance"]))       $details["distance"]    += (float)$result["distance"];
            if (!is_null((float)$result["elevationGain"]))  $details["d+"]          += (float)$result["elevationGain"];
            if (!is_null((float)$result["elevationLost"]))  $details["d-"]          += (float)$result["elevationLost"];
        }
        
        return $details;
    }
    
    public function findFirstActivityDateInLast12Months($userId) {
        $dbh        = $this->_em->getConnection();
        
        $sqlParts   = array(
            'select' => 'SELECT'
                .' SUBSTRING(a.issuedAt, 1, 10) as date',
            'from'  => ' FROM ks_activity a',
            'where' => ' WHERE a.user_id = :userId'
                .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
                .' AND a.isDisabled = FALSE'
                .' AND a.isValidate != FALSE',
            'group' => '',
            'order' => ' ORDER BY a.issuedAt ASC',
            'limit' => ' LIMIT 1'
        );
        
        $vars = array();
        $vars["userId"] = $userId;
        //Sur les 12 derniers mois
        $vars["startOn"] = date('Y-m-01', strtotime("- 11 month"));
        
        $activityTypes = array(
            'session',
            'session_team_sport',
            'session_endurance_on_earth',
            'session_endurance_under_water'
        );
        $sqlParts['where'] .= ' AND a.type in (\''.implode("','", $activityTypes).'\')';
        
        //var_dump(implode(' ', $sqlParts));
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $firstActivityDate = null;
        if( count( $results ) > 0)
             $firstActivityDate = $results[0]["date"];

        return $firstActivityDate;
    }
    
    public function findActivitiesInLast12Months($userId) {
        $dbh        = $this->_em->getConnection();
        
        $sqlParts   = array(
            'select' => 'SELECT'
                .' a.id, IFNULL(a.event_id, 0) as event_id, IFNULL((select e.id from ks_event e where e.user_id = a.user_id and activitySession_id = a.id), 0) as activityEventId ',
            'from'  => ' FROM ks_activity a',
            'where' => ' WHERE a.user_id = :userId'
                .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
                .' AND a.isDisabled = FALSE'
                .' AND a.isValidate != FALSE'
                .' AND a.event_id IS NULL'
                //.' AND a.id not in (select e.activitySession_id from ks_event e where (e.user_id = a.user_id OR a.club_id = e.club_id) and activitySession_id is not null)'
        );
        
        $vars = array();
        $vars["userId"] = $userId;
        //Sur les 12 derniers mois
        $vars["startOn"] = date('Y-m-01', strtotime("- 3 month"));
        
        $activityTypes = array(
            'session',
            'session_team_sport',
            'session_endurance_on_earth',
            'session_endurance_under_water'
        );
        $sqlParts['where'] .= ' AND a.type in (\''.implode("','", $activityTypes).'\')';
        
        $sqlParts['where'] .= ' ORDER by a.id asc';
        
        //var_dump($vars);var_dump(implode(' ', $sqlParts));        exit;
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $results;
    }
    
    public function findActivitiesInLast12MonthsWithNegPoints($userId) {
        $dbh        = $this->_em->getConnection();
        
        $sqlParts   = array(
            'select' => 'SELECT'
                .' a.id ',
            'from'  => ' FROM ks_activity a',
            'where' => ' WHERE a.user_id = :userId'
                .' AND SUBSTRING(a.issuedAt, 1, 10) >= :startOn'
                .' AND a.isDisabled = FALSE'
                .' AND a.isValidate != FALSE'
                .' AND not exists (select 1 from ks_activity_earns_points aep where aep.user_id = a.user_id and aep.activitySession_id = a.id)'
        );
        
        $vars = array();
        $vars["userId"] = $userId;
        //Sur les 12 derniers mois
        $vars["startOn"] = date('Y-m-01', strtotime("- 12 month"));
        
        $activityTypes = array(
            'session',
            'session_team_sport',
            'session_endurance_on_earth',
            'session_endurance_under_water'
        );
        $sqlParts['where'] .= ' AND a.type in (\''.implode("','", $activityTypes).'\')';
        
        $sqlParts['where'] .= ' ORDER by a.id asc';
        
        //var_dump(implode(' ', $sqlParts));
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $results;
    }
    
    /**
     * 
     * @param type $firstDayOfMonth
     * @param type $lastDayOfMonth
     */
    public function findActivitiesFromCoachingSessionId($userId, $activityId, $checkboxCT, $importActivityService, $suuntoApi) {
        $dbh            = $this->_em->getConnection();
        $activityRep    = $this->_em->getRepository('KsActivityBundle:Activity');
        
        $sqlParts   = array(
            'select' => 'SELECT a.id, acfs.id_website_activity_service, acfs.service_id, cs.name, DATE_FORMAT(e.startDate, "%d-%m-%y") as issuedAt, ((substring(a.duration,1,2) * 3600) + (substring(a.duration,4,2) * 60) + substring(a.duration,7,2)) as durationInSeconds, '
            .'                  a.distance , a.elevationGain, a.elevationLost, a.trackingDatas ',
            'from'  => ' FROM ks_activity a, ks_event e, ks_coaching_session cs, ks_activity_come_from_service acfs ',
            'where' => ' WHERE a.user_id = :userId'
            .' AND a.duration is not null'
            .' AND a.isDisabled = FALSE'
            .' AND a.isValidate != FALSE'
            .' AND a.event_id = e.id'
            .' AND e.coachingSession_id = (select e2.coachingSession_id from ks_activity a2, ks_event e2 where a2.id = :activityId and a2.event_id = e2.id)'
            .' AND cs.id = e.coachingSession_id'
            .' AND acfs.activity_id = a.id'
            .' ORDER BY e.startDate'
        );
        
        $vars = array();
        $vars["userId"] = $userId;
        $vars["activityId"] = $activityId;
        //$vars["startOn"] = $firstDayOfPeriod;
        //$vars["endOn"] = $lastDayOfPeriod;
        
        //var_dump(implode(' ', $sqlParts));exit;
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);
        
        $details = array(
            "name"      => "",
            "issuedAt"  => array(),
            "distance"  => array(),
            "d+"        => array(),
            "d-"        => array()
        );
        
        $getSuuntoLaps = false;
        while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($result["service_id"] == 5) {
                //Uniquement pour SUUNTO pour le moment, on parcourt les différentes activités et on ajoute les laps si pas présent (car importées avec modif des laps)
                if( !is_null($result["trackingDatas"])) {
                    $trackingDatas = json_decode($result["trackingDatas"], true);
                    if (isset($trackingDatas["laps"]) && !is_null($trackingDatas["laps"]) && count($trackingDatas["laps"]) > 2) {
                        //Les laps existent déjà, on ne fait rien.
                    }
                    else {
                        $activityInfos = array();
                        $activityInfos["laps"] = $suuntoApi->getMarks($result["id_website_activity_service"]);
                        $laps = $importActivityService->getFullLapsFromSuunto($activityInfos);
                        
                        $trackingDatas["laps"] = $laps;
                        $activity = $activityRep->find($result["id"]);
                        $activity->setTrackingDatas($trackingDatas);
                        $this->_em->persist($activity);
                        $this->_em->flush();
                    }
                }
            }
        }
        
        $stmt = $dbh->executeQuery(implode(' ', $sqlParts), $vars);
        while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_null($result["issuedAt"])) $details["issuedAt"][] = $result["issuedAt"];
            if (!is_null($result["name"])) $details["name"] = $result["name"];
            
            if( $result["trackingDatas"] != null ) {
                $trackingDatas = json_decode($result["trackingDatas"], true);
                
                if (!$checkboxCT && isset($trackingDatas["laps"]) && !is_null($trackingDatas["laps"]) && count($trackingDatas["laps"]) > 2) {
                    //S'il y a des laps issus de la montre et que l'utilisateur veut un comparatif sans le 1er et le dernier (pour avoir uniquement sur la zone de fractionné)
                    $lapId          = 0;
                    $distance       = 0;
                    $elevationGain  = 0;
                    $elevationLost  = 0;
                    $duration       = 0;
                    $averageHR      = 0;
                    foreach ($trackingDatas["laps"] as $lap) {
                        $lapId ++;
                        if ($lapId !=1 && $lapId != count($trackingDatas["laps"])) {
                            $distance       += $lap["distance"];
                            $elevationGain  += $lap["elevationGain"];
                            $elevationLost  += $lap["elevationLost"];
                            $duration       += $lap["duration"];
                            $averageHR      += $lap["averageHR"];
                        }
                    }
                    $details["distance"][]      = round($distance, 2);
                    $details["d+"][]            = $elevationGain;
                    $details["d-"][]            = $elevationLost;
                    $details["duration"][]      = $duration;
                    $details["averageSpeed"][]  = round($distance / $duration * 3600, 2);
                    $details["averageHR"][]     = round($averageHR / ($lapId -2), 0);
                }
                else {
                    if( isset( $trackingDatas["info"]["distance"] ) && is_null((float)$result["distance"])) {
                        $details["distance"][] = (float)$trackingDatas["info"]["distance"];
                    }
                    else $details["distance"][] = (float)$result["distance"];
                    
                    if( isset( $trackingDatas["info"]["D+"] ) && is_null((float)$result["elevationGain"])) {
                        $details["d+"][] = (int)$trackingDatas["info"]["D+"];
                    }
                    else $details["d+"][] = (float)$result["elevationGain"];
                    
                    if( isset( $trackingDatas["info"]["D-"] ) && is_null((float)$result["elevationLost"])) {
                        $details["d-"][] = (int)$trackingDatas["info"]["D-"];
                    }
                    else $details["d-"][] = (float)$result["elevationLost"];
                    
                    if (!is_null($result["durationInSeconds"])) $details["duration"][] = (float)$result["durationInSeconds"];
            
                    if (!is_null((float)$result["durationInSeconds"]) && !is_null((float)$result["distance"]) && (float)$result["durationInSeconds"] !=0) $details["averageSpeed"][] = round((float)$result["distance"] / (float)$result["durationInSeconds"] * 3600, 2);
                    else $details["averageSpeed"][] = 0;
                    
                    //Gestion spécifique pour récupérer moyenne FC
                    if ($trackingDatas["info"]["issetHeartRates"] == true) {
                        $sum =0;
                        $count =0;
                        foreach( $trackingDatas["waypoints"] as $waypoint ) {
                            $sum += $waypoint["heartRate"];
                            $count ++;
                        }
                        if ($count !=0) $details["averageHR"][] = round($sum / $count);
                        else $details["averageHR"][] = 0;
                    }
                    else $details["averageHR"][] = 0;

                    //Gestion spécifique pour récupérer moyenne Température
                    if ($trackingDatas["info"]["issetTemperatures"] == true) {
                        $sum =0;
                        $count =0;
                        foreach( $trackingDatas["waypoints"] as $waypoint ) {
                            $sum += $waypoint["temperature"];
                            $count ++;
                        }
                        if ($count !=0) $details["averageTemperature"][] = round($sum / $count);
                        else $details["averageTemperature"][] = 0;
                    }
                    else $details["averageTemperature"][] = 0;
                }
            }
            else {
                if (!is_null((float)$result["distance"]))       $details["distance"][]    = (float)$result["distance"];
                if (!is_null((float)$result["elevationGain"]))  $details["d+"][]          = (float)$result["elevationGain"];
                if (!is_null((float)$result["elevationLost"]))  $details["d-"][]          = (float)$result["elevationLost"];

                if (!is_null($result["durationInSeconds"])) $details["duration"][] = (float)$result["durationInSeconds"];
            
                if (!is_null((float)$result["durationInSeconds"]) && !is_null((float)$result["distance"]) && (float)$result["durationInSeconds"] !=0) $details["averageSpeed"][] = round((float)$result["distance"] / (float)$result["durationInSeconds"] * 3600, 2);
                else $details["averageSpeed"][] = 0;

                $details["averageHR"][] = 0;
                $details["averageTemperature"][] = 0;
            }
        }
        
        return $details;
    }
}