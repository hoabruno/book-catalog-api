<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_catalog_and_a_book_page_are_public(): void
    {
        $book = Book::factory()->for(Author::factory()->state(['name' => 'Jules Verne']))->create(['title' => 'Michel Strogoff']);

        $this->get('/')->assertOk()->assertSee('Michel Strogoff')->assertSee('Jules Verne')->assertDontSee('Ajouter un livre');
        $this->get("/livres/{$book->slug}")->assertOk()->assertSee($book->isbn->formatted())->assertDontSee('Modifier');
        $this->get('/livres/inconnu')->assertNotFound();
    }

    #[Test]
    public function management_pages_require_login_then_the_editor_role(): void
    {
        $this->get('/gestion/livres/nouveau')->assertRedirect('/connexion');

        $this->actingAs(User::factory()->create())->get('/gestion/livres/nouveau')->assertForbidden();
    }

    #[Test]
    public function an_editor_logs_in_and_adds_a_book_through_the_form(): void
    {
        User::factory()->editor()->create(['email' => 'editor@example.test', 'password' => 'correct-horse']);
        $author = Author::factory()->create();

        $this->post('/connexion', ['email' => 'editor@example.test', 'password' => 'correct-horse'])->assertRedirect('/');
        $this->get('/gestion/livres/nouveau')->assertOk()->assertSee('Nouveau livre');

        $this->post('/gestion/livres', [
            'author_id' => $author->id,
            'isbn' => '978-0-306-40615-7',
            'title' => 'Le Petit Prince',
            'format' => 'hardcover',
        ])->assertRedirect('/livres/le-petit-prince');

        $this->get('/livres/le-petit-prince')->assertOk()->assertSee('Le Petit Prince')->assertSee('Modifier');
    }

    #[Test]
    public function form_errors_are_shown_in_french(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->from('/gestion/livres/nouveau')
            ->post('/gestion/livres', ['isbn' => '123'])
            ->assertRedirect('/gestion/livres/nouveau')
            ->assertSessionHasErrors(['isbn' => 'Le champ isbn doit être un ISBN-10 ou ISBN-13 valide.']);
    }

    #[Test]
    public function login_failures_stay_generic(): void
    {
        $this->post('/connexion', ['email' => 'nobody@example.test', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
        $this->assertGuest();
    }
}
