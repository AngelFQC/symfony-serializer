<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xabbuh\XApi\Serializer\Symfony\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Common\Exception\XApiException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\Object as LegacyStatementObject;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementObject;
use Xabbuh\XApi\Model\StatementReference;

/**
 * Normalizes and denormalizes xAPI statements.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementNormalizer extends Normalizer
{
    public const VALID_PROPERTIES = [
        'id',
        'actor',
        'verb',
        'object',
        'result',
        'context',
        'timestamp',
        'stored',
        'authority',
        'version',
        'attachments',
    ];

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if (!$object instanceof Statement) {
            return null;
        }

        $data = array(
            'actor' => $this->normalizeAttribute($object->getActor(), $format, $context),
            'verb' => $this->normalizeAttribute($object->getVerb(), $format, $context),
            'object' => $this->normalizeAttribute($object->getObject(), $format, $context),
        );

        if (null !== $id = $object->getId()) {
            $data['id'] = $id->getValue();
        }

        if (null !== $authority = $object->getAuthority()) {
            $data['authority'] = $this->normalizeAttribute($authority, $format, $context);
        }

        if (null !== $result = $object->getResult()) {
            $data['result'] = $this->normalizeAttribute($result, $format, $context);
        }

        if (null !== $result = $object->getCreated()) {
            $data['timestamp'] = $this->normalizeAttribute($result, $format, $context);
        }

        if (null !== $result = $object->getStored()) {
            $data['stored'] = $this->normalizeAttribute($result, $format, $context);
        }

        if (null !== $object->getContext()) {
            $data['context'] = $this->normalizeAttribute($object->getContext(), $format, $context);
        }

        if (null !== $attachments = $object->getAttachments()) {
            $data['attachments'] = $this->normalizeAttribute($attachments, $format, $context);
        }

        if (null !== $version = $object->getVersion()) {
            $data['version'] = $version;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Statement;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $dataProperties = array_keys($data);
        $diff = array_diff($dataProperties, self::VALID_PROPERTIES);

        if (count($diff) > 0) {
            throw new UnexpectedValueException('Some statements properties are not valid.');
        }

        $version = null;

        if (isset($data['version'])) {
            $version = $data['version'];

            if (!preg_match('/^1\.0(?:\.\d+)?$/', $version)) {
                throw new UnsupportedStatementVersionException(sprintf('Statements at version "%s" are not supported.', $version));
            }
        }

        $id = null;

        if (isset($data['id'])) {
            if (!is_string($data['id'])) {
                throw new UnexpectedValueException('Statement ID is not valid.');
            }

            $id = StatementId::fromString($data['id']);
        }

        if (empty($data['actor'])) {
            throw new InvalidArgumentException('Statement actor is missing.');
        }

        if (empty($data['verb'])) {
            throw new InvalidArgumentException('Statement verb is missing.');
        }

        if (empty($data['object'])) {
            throw new InvalidArgumentException('Statement object is missing.');
        }

        $actor = $this->denormalizeData($data['actor'], 'Xabbuh\XApi\Model\Actor', $format, $context);
        $verb = $this->denormalizeData($data['verb'], 'Xabbuh\XApi\Model\Verb', $format, $context);

        if (class_exists(StatementObject::class)) {
            $object = $this->denormalizeData($data['object'], StatementObject::class, $format, $context);
        } else {
            $object = $this->denormalizeData($data['object'], LegacyStatementObject::class, $format, $context);
        }

        if ($verb->isVoidVerb() && !$object instanceof StatementReference) {
            throw new \UnexpectedValueException('Statement verb voided does not use object "StatementRef"');
        }

        $result = null;
        $authority = null;
        $created = null;
        $stored = null;
        $statementContext = null;
        $attachments = null;

        if (isset($data['result'])) {
            $result = $this->denormalizeData($data['result'], 'Xabbuh\XApi\Model\Result', $format, $context);
        }

        if (isset($data['authority'])) {
            $authority = $this->denormalizeData($data['authority'], 'Xabbuh\XApi\Model\Actor', $format, $context);
        }

        if (isset($data['timestamp'])) {
            $created = $this->denormalizeData($data['timestamp'], 'DateTime', $format, $context);
        }

        if (isset($data['stored'])) {
            $stored = $this->denormalizeData($data['stored'], 'DateTime', $format, $context);
        }

        if (isset($data['context'])) {
            if (!$object instanceof Activity) {
                if (isset($data['context']['revision'])) {
                    throw new \UnexpectedValueException('The "revision" property is not valid with the object type.');
                }

                if (isset($data['context']['platform'])) {
                    throw new \UnexpectedValueException('The "platform" property is not valid with the object type.');
                }
            }

            $statementContext = $this->denormalizeData($data['context'], 'Xabbuh\XApi\Model\Context', $format, $context);
        }

        if (isset($data['attachments'])) {
            if (empty($data['attachments'][0]) || !is_array($data['attachments'][0])) {
                throw new XApiException('Statement attachments are not valid.');
            }

            $attachments = $this->denormalizeData($data['attachments'], 'Xabbuh\XApi\Model\Attachment[]', $format, $context);
        }

        return new Statement($id, $actor, $verb, $object, $result, $authority, $created, $stored, $statementContext, $attachments, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return 'Xabbuh\XApi\Model\Statement' === $type;
    }
}
