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

use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Xabbuh\XApi\Model\Result;

/**
 * Normalizes and denormalizes xAPI statement results.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class ResultNormalizer extends Normalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if (!$object instanceof Result) {
            return null;
        }

        $data = array();

        if (null !== $object->getScore()) {
            $data['score'] = $this->normalizeAttribute($object->getScore(), 'Xabbuh\XApi\Model\Score', $context);
        }

        if (null !== $success = $object->getSuccess()) {
            $data['success'] = $success;
        }

        if (null !== $completion = $object->getCompletion()) {
            $data['completion'] = $completion;
        }

        if (null !== $response = $object->getResponse()) {
            $data['response'] = $response;
        }

        if (null !== $duration = $object->getDuration()) {
            $data['duration'] = $duration;
        }

        if (null !== $extensions = $object->getExtensions()) {
            $data['extensions'] = $this->normalizeAttribute($extensions, 'Xabbuh\XApi\Model\Extensions', $context);
        }

        if (empty($data)) {
            return new \stdClass();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Result;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $score = isset($data['score']) ? $this->denormalizeData($data['score'], 'Xabbuh\XApi\Model\Score', $format, $context) : null;
        $success = null;
        $completion = null;

        if (isset($data['success'])) {
            if (!is_bool($data['success']) || $data['success'] === (string) $data['success']) {
                throw new UnexpectedValueException('The success property in result has a wrong data type.');
            }

            $success = $data['success'];
        }

        if (isset($data['completion'])) {
            if (!is_bool($data['completion']) || $data['completion'] === (string) $data['completion']) {
                throw new UnexpectedValueException('The completion property in result has a wrong data type.');
            }

            $completion = $data['completion'];
        }

        $response = isset($data['response']) ? $data['response'] : null;

        if (null !== $response && !is_string($response)) {
            throw new \InvalidArgumentException('A "response" property is not a string.');
        }

        $duration = isset($data['duration']) ? $data['duration'] : null;

        if (null !== $duration) {
            $duration = preg_replace_callback(
                '/\d+[.,]\d+/',
                function ($matches) {
                    return floor($matches[0]);
                },
                $duration
            );

            try {
                new \DateInterval($duration);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('A "duration" property is not formatted to ISO 8601.');
            }
        }

        $extensions = isset($data['extensions']) ? $this->denormalizeData($data['extensions'], 'Xabbuh\XApi\Model\Extensions', $format, $context) : null;

        return new Result($score, $success, $completion, $response, $duration, $extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return 'Xabbuh\XApi\Model\Result' === $type;
    }
}
