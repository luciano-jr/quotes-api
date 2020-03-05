<?php

declare(strict_types=1);

namespace App\Controller;

use App\FormType\FormErrorHandlerInterface;
use App\FormType\FormErrorHandlerTrait;
use App\FormType\ShoudRequestTypeForm;
use App\QuoteService\QuoteServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShoutController extends AbstractController implements FormErrorHandlerInterface
{
    use FormErrorHandlerTrait;

    private AdapterInterface $cache;

    private QuoteServiceInterface $quoteService;

    public function __construct(
        QuoteServiceInterface $quoteService,
        AdapterInterface $cache
    ) {
        $this->cache = $cache;
        $this->quoteService = $quoteService;
    }

    public function shout(Request $request, string $author): Response
    {
        $form = $this->createForm(ShoudRequestTypeForm::class);
        $requestData = array_merge(['author' => $author], $request->query->all());
        $form->submit($requestData);

        if ($form->isSubmitted() && $form->isValid()) {
            $should = $form->getData();

            $cacheKey = $should->getAuthor();

            $cachedItem = $this->cache->getItem($cacheKey);

            if (false === $cachedItem->isHit()) {
                $quotes = $this->quoteService->getQuotes($should->getAuthor(), $should->getLimit());
                $cachedItem->set($quotes);
                $this->cache->save($cachedItem);
            }
            $quotes = $this->quoteService->getQuotes($should->getAuthor(), $should->getLimit());

            return new JsonResponse($cachedItem->get(), Response::HTTP_OK);
        }

        $errors = $this->getErrors($form);

        $data = [
            'type' => 'validation_error',
            'title' => 'There was a validation error',
            'errors' => $errors
        ];

        return new JsonResponse($data, Response::HTTP_BAD_REQUEST);
    }
}
