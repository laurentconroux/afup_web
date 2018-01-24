<?php


namespace AppBundle\Association\Model\Repository;

use AppBundle\Association\Model\CompanyMember;
use AppBundle\Association\Model\User;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SubscriptionRepository extends Repository implements MetadataInitializer
{
    public function getStats(\DateInterval $interval)
    {
        // Personnes physiques

        // Personnes morales
    }

    /**
     * Add a condition about the type of users: physical, legal or all
     *
     * @param SelectInterface $queryBuilder
     * @param $userType
     */
    private function addUserTypeCondition(SelectInterface $queryBuilder, $userType)
    {
        if ($userType === UserRepository::USER_TYPE_PHYSICAL) {
            $queryBuilder->where('id_personne_morale = 0');
        } elseif ($userType === UserRepository::USER_TYPE_COMPANY) {
            $queryBuilder->where('id_personne_morale <> 0');
        } elseif ($userType !== UserRepository::USER_TYPE_ALL) {
            throw new \UnexpectedValueException(sprintf('Unknown user type "%s"', $userType));
        }
    }

    /**
     * @inheritDoc
     */
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = [])
    {
        $metadata = new Metadata($serializerFactory);

        $metadata->setEntity(User::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase($options['database']);
        $metadata->setTable('afup_cotisations');

        $metadata
            ->addField([
                'columnName' => 'id',
                'fieldName' => 'id',
                'primary'       => true,
                'autoincrement' => true,
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'date_debut',
                'fieldName' => 'startDate',
                'type' => 'datetime',
                'serializer_options' => [
                    'unserialize' => ['unSerializeUseFormat' => true, 'format' => 'U']
                ]
            ])
            ->addField([
                'columnName' => 'date_fin',
                'fieldName' => 'endDate',
                'type' => 'datetime',
                'serializer_options' => [
                    'unserialize' => ['unSerializeUseFormat' => true, 'format' => 'U']
                ]
            ])
            ->addField([
                'columnName' => 'type_personne',
                'fieldName' => 'userType',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'id_personne',
                'fieldName' => 'userId',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'montant',
                'fieldName' => 'amount',
                'type' => 'float'
            ])
            ->addField([
                'columnName' => 'type_reglement',
                'fieldName' => 'paymentType',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'informations_reglement',
                'fieldName' => 'paymentDetails',
                'type' => 'string'
            ])
            ->addField([
                'columnName' => 'numero_facture',
                'fieldName' => 'invoiceNumber',
                'type' => 'string'
            ])
            ->addField([
                'columnName' => 'commentaires',
                'fieldName' => 'comments',
                'type' => 'string'
            ])
            ->addField([
                'columnName' => 'token',
                'fieldName' => 'token',
                'type' => 'string'
            ])
        ;

        return $metadata;
    }
}
