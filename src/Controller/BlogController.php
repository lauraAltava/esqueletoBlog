<?php

namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class BlogController extends AbstractController
{
    #[Route("/blog/buscar", name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine,  Request $request, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm') ?? "";
        $posts = null;
        if (!empty($searchTerm)){
            $posts = $repository->findByText($request->query->get('searchTerm') ?? "");
            return $this->render('blog/blog.html.twig', [
                'posts' => $posts,
            ]);

        }else{
           
       return new Response("No se encontró nada");
           
        }
            

    } 
   
    #[Route("/blog/new", name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger, EntityManagerInterface $entityManager  ): Response {
        $user = $this->getUser();
        
       
        $post = new Post();
        $formulario = $this->createForm(PostFormType::class, $post);
        $formulario->handleRequest($request);
        


    if ($formulario->isSubmitted() && $formulario->isValid()) {
        $post = $formulario->getData();
        $file = $formulario->get('Image')->getData();
        if ($file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            // this is needed to safely include the file name as part of the URL
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
    
            // Move the file to the directory where images are stored
            try {
    
                $file->move(
                    $this->getParameter('images_directory'), $newFilename
                );
               
            } catch (FileException $e) {
               
            }
            $post->setImage($newFilename);
            $post->setNumLikes(0);
            $post->setNumComments(0);
            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setNumViews(0);
            $post->setUser($user);
            
           }
           
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($post);
        $entityManager->flush();
    }
    
    return $this->render('blog/new_post.html.twig', array(
        'form' => $formulario->createView()));

}

    #[Route("/single_post/{slug}/like", name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug, EntityManagerInterface $entityManager ): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post= $repository->findOneBy(["Slug" => $slug]);
        if($post){
            
            $post->addLikes();
            
            $entityManager->persist($post);
            $entityManager->flush();
            return $this->redirectToRoute('blog');
        }
    }

    #[Route("/blog/{slug}/comment", name: 'post_comment')]
    public function comment(ManagerRegistry $doctrine, $slug, EntityManagerInterface $entityManager ): Response
    {
        $user = $this->getUser();
        
       
        $comment = new Comment();
        $formulario = $this->createForm(CommentFormType::class, $comment);
        $formulario->handleRequest($request);
        
        $repository = $doctrine->getRepository(Post::class);
        $post= $repository->findOneBy(["Slug" => $slug]);
        if($post){
            
            $post->setText();
            
            $entityManager->persist($post);
            $entityManager->flush();
            return $this->redirectToRoute('blog');
        }
    }

    #[Route("/blog", name: 'blog')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();
        
        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route("/single_post/{slug}", name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug ): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(['Slug' => $slug]);

        return $this->render('blog/single_post.html.twig', [
            'post' => $post,

        ]);
    }
}
