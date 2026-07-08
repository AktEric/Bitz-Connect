# Design Document — Refatoramento Arquitetural: Hotel

## Visão Geral

Este documento descreve a arquitetura técnica do refatoramento do sistema de hotéis, cobrindo a separação definitiva entre perfis de pessoa e de empresa, o fluxo de vínculo colaborador–hotel, o sistema de seguidores, permissões administrativas granulares, fluxo de reivindicação de hotel e novos componentes reutilizáveis de UI.

O sistema segue a arquitetura de domínio já estabelecida no projeto:
- **Backend**: Laravel com camadas `Domain` (Services, DTOs, Repositories) + `Http` (Controllers, Requests, Resources, Policies)
- **Frontend**: React + TypeScript com React Query, separação por `hooks`, `services`, `components` e `types`

---

## 1. Banco de Dados — Novas Migrações

As migrações devem ser numeradas a partir de `2025_01_01_000021` para seguir a convenção do projeto.

### 1.1 `add_hotel_id_verified_to_experiences`

```php
Schema::table('experiences', function (Blueprint $table) {
    $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete()->after('user_id');
    $table->boolean('verified')->default(false)->after('hotel_id');
});
```

### 1.2 `add_status_to_hotel_users`

Altera a tabela `hotel_users` para suportar o fluxo de aprovação de colaboradores. O campo `role` passa a aceitar `'employee'` além de `'admin'`; o campo `status` controla o estado do vínculo.

```php
Schema::table('hotel_users', function (Blueprint $table) {
    // Alterar role para incluir 'employee'
    $table->enum('role', ['admin', 'employee'])->default('admin')->change();
    // Novo campo status
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('role');
});
```

> **Nota**: Como o banco usa PostgreSQL, utilizar `DB::statement` para alterar o tipo ENUM existente, ou recriar a coluna.

### 1.3 `create_hotel_followers_table`

```php
Schema::create('hotel_followers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('created_at')->useCurrent();
    $table->unique(['hotel_id', 'user_id']);
});
```

### 1.4 `add_followers_count_to_hotels`

```php
Schema::table('hotels', function (Blueprint $table) {
    $table->unsignedInteger('followers_count')->default(0)->after('contact_email');
});
```

### 1.5 `add_segment_to_hotels`

```php
Schema::table('hotels', function (Blueprint $table) {
    $table->string('segment')->nullable()->after('name');
});
```

### 1.6 `create_hotel_claims_table`

```php
Schema::create('hotel_claims', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->text('message')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['hotel_id', 'user_id', 'status']);
});
```

---

## 2. Modelos Eloquent

### 2.1 `Hotel` — campos adicionados

```php
// Adicionar ao $fillable:
'segment',
'followers_count',

// Novos relacionamentos:
public function followers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'hotel_followers')->withTimestamps();
}

public function hotelFollowers(): HasMany
{
    return $this->hasMany(HotelFollower::class);
}

public function claims(): HasMany
{
    return $this->hasMany(HotelClaim::class);
}

/** Colaboradores aprovados */
public function employees(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'hotel_users')
        ->withPivot(['role', 'status'])
        ->wherePivot('role', 'employee')
        ->wherePivot('status', 'approved')
        ->withTimestamps();
}
```

### 2.2 `HotelUser` — campos adicionados

```php
// Adicionar ao $fillable:
'status',

// Casts:
protected function casts(): array
{
    return [
        'status' => 'string', // 'pending' | 'approved' | 'rejected'
        'role'   => 'string', // 'admin' | 'employee'
    ];
}

// Scopes auxiliares:
public function scopeApproved(Builder $query): Builder
{
    return $query->where('status', 'approved');
}

public function scopePending(Builder $query): Builder
{
    return $query->where('status', 'pending');
}
```

### 2.3 `Experience` — campos adicionados

```php
// Adicionar ao $fillable:
'hotel_id',
'verified',

// Casts:
'verified' => 'boolean',

// Relacionamento:
public function hotel(): BelongsTo
{
    return $this->belongsTo(Hotel::class);
}
```

### 2.4 Novo modelo `HotelFollower`

Arquivo: `app/Models/HotelFollower.php`

```php
class HotelFollower extends Model
{
    public $timestamps = false;
    protected $fillable = ['hotel_id', 'user_id'];
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
}
```

### 2.5 Novo modelo `HotelClaim`

Arquivo: `app/Models/HotelClaim.php`

```php
class HotelClaim extends Model
{
    protected $fillable = ['hotel_id', 'user_id', 'status', 'message', 'reviewed_at', 'reviewed_by'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function hotel(): BelongsTo    { return $this->belongsTo(Hotel::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
```

---

## 3. Serviços de Domínio

Todos os serviços residem em `app/Domain/Hotel/Services/`.

### 3.1 `EmployeeService`

Responsável por todo o ciclo de vínculo colaborador–hotel.

```php
class EmployeeService
{
    public function __construct(
        private readonly HotelRepositoryInterface $hotelRepository,
    ) {}

    /**
     * Vincula uma experiência a um hotel e cria o registro hotel_users pending.
     * Requisitos: 6.3, 6.4
     */
    public function linkExperience(Experience $experience, Hotel $hotel): void
    {
        $experience->update(['hotel_id' => $hotel->id]);

        HotelUser::firstOrCreate(
            ['hotel_id' => $hotel->id, 'user_id' => $experience->user_id],
            ['role' => 'employee', 'status' => 'pending'],
        );
        // Dispara evento para notificar admins do hotel (Requisito 6.5)
    }

    /**
     * Aprova vínculo: atualiza hotel_users e experiences.verified.
     * Requisitos: 6.6
     */
    public function approve(HotelUser $hotelUser): void
    {
        $hotelUser->update(['status' => 'approved']);
        Experience::where('hotel_id', $hotelUser->hotel_id)
            ->where('user_id', $hotelUser->user_id)
            ->update(['verified' => true]);
        // Dispara evento de notificação ao colaborador (Requisito 6.8)
    }

    /**
     * Rejeita vínculo.
     * Requisitos: 6.7, 6.8
     */
    public function reject(HotelUser $hotelUser): void
    {
        $hotelUser->update(['status' => 'rejected']);
        // Dispara evento de notificação ao colaborador
    }

    /**
     * Lista colaboradores aprovados de um hotel.
     * Requisito: 5.1
     */
    public function listApproved(Hotel $hotel): Collection
    {
        return $hotel->hotelUsers()
            ->with('user')
            ->where('role', 'employee')
            ->where('status', 'approved')
            ->get();
    }

    /**
     * Lista solicitações pendentes de um hotel (admin only).
     * Requisito: 6.4
     */
    public function listPending(Hotel $hotel): Collection
    {
        return $hotel->hotelUsers()
            ->with('user')
            ->where('role', 'employee')
            ->where('status', 'pending')
            ->get();
    }
}
```

### 3.2 `FollowerService`

```php
class FollowerService
{
    /**
     * Segue hotel: insere em hotel_followers e incrementa contador.
     * Requisitos: 8.2, 8.4
     */
    public function follow(Hotel $hotel, User $user): void
    {
        if ($hotel->hotelFollowers()->where('user_id', $user->id)->exists()) {
            throw new ConflictException('Você já segue este hotel.');
        }
        $hotel->hotelFollowers()->create(['user_id' => $user->id]);
        $hotel->increment('followers_count');
    }

    /**
     * Deixa de seguir: remove de hotel_followers e decrementa contador.
     * Requisito: 8.3
     */
    public function unfollow(Hotel $hotel, User $user): void
    {
        $hotel->hotelFollowers()->where('user_id', $user->id)->delete();
        $hotel->decrement('followers_count');
    }
}
```

### 3.3 `ClaimService`

```php
class ClaimService
{
    /**
     * Cria uma solicitação de reivindicação pending.
     * Requisitos: 7.2, 7.6
     */
    public function claim(Hotel $hotel, User $user, ?string $message = null): HotelClaim
    {
        $exists = HotelClaim::where('hotel_id', $hotel->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw new ConflictException('Você já possui uma solicitação pendente para este hotel.');
        }

        $claim = HotelClaim::create([
            'hotel_id' => $hotel->id,
            'user_id'  => $user->id,
            'status'   => 'pending',
            'message'  => $message,
        ]);

        // Notifica platform_admins (Requisito 7.3)
        return $claim;
    }

    /**
     * Aprova reivindicação e cria hotel_users admin.
     * Requisito: 7.4
     */
    public function approve(HotelClaim $claim, User $reviewer): void
    {
        $claim->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
        HotelUser::create([
            'hotel_id' => $claim->hotel_id,
            'user_id'  => $claim->user_id,
            'role'     => 'admin',
            'status'   => 'approved',
        ]);
        // Notifica usuário reivindicante (Requisito 7.5)
    }

    /**
     * Rejeita reivindicação.
     * Requisito: 7.5
     */
    public function reject(HotelClaim $claim, User $reviewer): void
    {
        $claim->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
        // Notifica usuário reivindicante
    }
}
```

### 3.4 `HotelService` — extensão

Método adicional para garantir invariante de admin e suporte ao campo `segment`:

```php
/**
 * Garante que o hotel possui ao menos um admin (Requisito 9.8).
 */
public function hasAtLeastOneAdmin(Hotel $hotel): bool
{
    return $hotel->hotelUsers()->where('role', 'admin')->where('status', 'approved')->exists();
}

/**
 * Promove employee a admin (Requisito 9.6).
 */
public function promoteToAdmin(Hotel $hotel, User $target): void
{
    $hotel->hotelUsers()->where('user_id', $target->id)->update(['role' => 'admin']);
}

/**
 * Rebaixa admin a employee, validando invariante (Requisito 9.7, 9.8).
 */
public function demoteToEmployee(Hotel $hotel, User $target): void
{
    $adminCount = $hotel->hotelUsers()->where('role', 'admin')->where('status', 'approved')->count();
    if ($adminCount <= 1) {
        throw new UnprocessableEntityException('O hotel deve manter ao menos um administrador.');
    }
    $hotel->hotelUsers()->where('user_id', $target->id)->update(['role' => 'employee']);
}
```

---

## 4. DTOs

Todos em `app/Domain/Hotel/DTOs/`.

### 4.1 `LinkExperienceDTO`
```php
class LinkExperienceDTO
{
    public function __construct(
        public readonly int $experienceId,
        public readonly int $hotelId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            experienceId: (int) $data['experience_id'],
            hotelId:      (int) $data['hotel_id'],
        );
    }
}
```

### 4.2 `ClaimHotelDTO`
```php
class ClaimHotelDTO
{
    public function __construct(
        public readonly ?string $message,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(message: $data['message'] ?? null);
    }
}
```

### 4.3 `UpdateHotelDTO` — extensão

Adicionar campo `segment` ao DTO existente:

```php
// Adicionar ao construtor:
public readonly ?string $segment,

// Adicionar ao fromArray():
segment: $data['segment'] ?? null,
```

### 4.4 `CreateHotelDTO` — extensão

```php
// Adicionar ao construtor:
public readonly ?string $segment,

// Adicionar ao fromArray():
segment: $data['segment'] ?? null,
```

---

## 5. Repositórios

### 5.1 `HotelRepositoryInterface` — métodos adicionados

```php
/** Busca hotéis onde o usuário é admin ou employee aprovado. */
public function findByMember(User $user): LengthAwarePaginator;

/** Carrega hotel com contagens otimizadas para o perfil público. */
public function findForProfile(string $slug, ?int $authUserId = null): ?Hotel;

/** Verifica se usuário segue o hotel. */
public function isFollowing(Hotel $hotel, User $user): bool;
```

### 5.2 `EloquentHotelRepository` — implementações

```php
public function findByMember(User $user): LengthAwarePaginator
{
    return Hotel::whereHas('hotelUsers', fn ($q) =>
        $q->where('user_id', $user->id)
          ->where('status', 'approved')
          ->whereIn('role', ['admin', 'employee'])
    )->paginate(15);
}

public function findForProfile(string $slug, ?int $authUserId = null): ?Hotel
{
    return Hotel::where('slug', $slug)
        ->withCount([
            'hotelUsers as employees_count' => fn ($q) =>
                $q->where('role', 'employee')->where('status', 'approved'),
            'jobs as jobs_count' => fn ($q) =>
                $q->where('status', 'active'),
        ])
        ->first()
        ?->setAttribute(
            'is_following',
            $authUserId
                ? HotelFollower::where('hotel_id', fn($q) => $q->select('id'))
                               ->where('user_id', $authUserId)->exists()
                : false
        );
}

public function isFollowing(Hotel $hotel, User $user): bool
{
    return $hotel->hotelFollowers()->where('user_id', $user->id)->exists();
}
```

---

## 6. Policies Laravel

Arquivo: `app/Policies/HotelPolicy.php`

```php
class HotelPolicy
{
    /** Apenas admins aprovados podem editar o perfil (Requisito 9.1). */
    public function update(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /** Apenas admins podem fazer upload de mídia. */
    public function uploadMedia(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /** Apenas admins podem criar/excluir vagas (Requisito 9.3). */
    public function manageJobs(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /** Apenas admins podem aprovar/rejeitar vínculos (Requisito 9.5). */
    public function manageEmployees(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /** Apenas admins podem promover/rebaixar roles (Requisitos 9.6, 9.7). */
    public function manageRoles(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    private function isAdmin(User $user, Hotel $hotel): bool
    {
        return $hotel->hotelUsers()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->exists();
    }
}
```

Arquivo: `app/Policies/HotelClaimPolicy.php`

```php
class HotelClaimPolicy
{
    /** Apenas platform_admins podem aprovar/rejeitar reivindicações (Requisito 7.3). */
    public function review(User $user): bool
    {
        return $user->role === 'platform_admin';
    }
}
```

---

## 7. API Resources

### 7.1 `HotelResource` — extensão

Adicionar campos ao `toArray()` do `HotelResource` existente:

```php
'segment'         => $this->segment,
'followers_count' => $this->followers_count,
'employees_count' => $this->whenCounted('employees_count'),
'jobs_count'      => $this->whenCounted('jobs_count'),
'is_following'    => $this->when(isset($this->is_following), $this->is_following),
'has_admin'       => $this->hotelUsers()->where('role', 'admin')->exists(),
```

### 7.2 Novo `EmployeeResource`

Arquivo: `app/Http/Resources/EmployeeResource.php`

```php
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $experience = $this->experiences()
            ->where('hotel_id', $this->pivot->hotel_id)
            ->first();

        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'avatar_url' => $this->avatar_url,
            'title'      => $experience?->title,
            'started_at' => $experience?->started_at,
            'verified'   => $experience?->verified ?? false,
            'hotel_user' => [
                'role'       => $this->pivot->role,
                'status'     => $this->pivot->status,
                'created_at' => $this->pivot->created_at,
            ],
        ];
    }
}
```

### 7.3 Novo `HotelClaimResource`

```php
class HotelClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'hotel_id'    => $this->hotel_id,
            'user_id'     => $this->user_id,
            'status'      => $this->status,
            'message'     => $this->message,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
        ];
    }
}
```

---

## 8. Novos Endpoints de API

Todos os endpoints abaixo são adicionados ao `HotelController` ou a novos controllers específicos dentro de `app/Http/Controllers/Api/V1/`.

### 8.1 Tabela de Endpoints

| Método   | URI                                         | Controlador / Método             | Middleware  | Requisito |
|----------|---------------------------------------------|----------------------------------|-------------|-----------|
| `POST`   | `/hotels/:slug/follow`                      | `HotelController@follow`         | `auth`      | 8.2       |
| `DELETE` | `/hotels/:slug/follow`                      | `HotelController@unfollow`       | `auth`      | 8.3       |
| `GET`    | `/hotels/:slug/employees`                   | `HotelController@employees`      | —           | 5.1       |
| `GET`    | `/hotels/:slug/pending-employees`           | `HotelController@pendingEmployees` | `auth`    | 6.4       |
| `PATCH`  | `/hotels/:slug/employees/:userId/approve`   | `HotelController@approveEmployee`| `auth`      | 6.6       |
| `PATCH`  | `/hotels/:slug/employees/:userId/reject`    | `HotelController@rejectEmployee` | `auth`      | 6.7       |
| `POST`   | `/hotels/:slug/claim`                       | `HotelController@claim`          | `auth`      | 7.2       |
| `PATCH`  | `/hotels/:slug/promote/:userId`             | `HotelController@promote`        | `auth`      | 9.6       |
| `PATCH`  | `/hotels/:slug/demote/:userId`              | `HotelController@demote`         | `auth`      | 9.7       |

### 8.2 Esquema das respostas principais

**`GET /hotels/:slug`** (estendido):
```json
{
  "data": {
    "uuid": "...", "name": "Grand Hotel", "slug": "grand-hotel",
    "segment": "Luxo", "followers_count": 120,
    "employees_count": 15, "jobs_count": 3,
    "is_following": false, "has_admin": true
  }
}
```

**`GET /hotels/:slug/employees`**:
```json
{
  "data": [
    {
      "uuid": "...", "name": "João Silva", "avatar_url": "...",
      "title": "Recepcionista", "started_at": "2022-01-01",
      "verified": true,
      "hotel_user": { "role": "employee", "status": "approved" }
    }
  ]
}
```

### 8.3 Tratamento de erros

| Situação                              | Status HTTP | Mensagem                                    |
|---------------------------------------|-------------|---------------------------------------------|
| Seguir hotel já seguido               | 409         | "Você já segue este hotel."                 |
| Reivindicação duplicada pending       | 409         | "Você já possui solicitação pendente."      |
| Operação sem permissão (não-admin)    | 403         | "Ação não autorizada."                      |
| Hotel não encontrado                  | 404         | "Hotel não encontrado."                     |
| Remoção de último admin               | 422         | "O hotel deve manter ao menos um administrador." |
| Usuário não autenticado em rota auth  | 401         | "Não autenticado."                          |

---

## 9. Roteamento Backend (Laravel)

Adicionar ao `routes/api.php` dentro do grupo `v1` já existente:

```php
Route::prefix('hotels/{hotel:slug}')->group(function () {
    // Públicos
    Route::get('/employees', [HotelController::class, 'employees']);

    // Autenticados
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/follow',    [HotelController::class, 'follow']);
        Route::delete('/follow',  [HotelController::class, 'unfollow']);
        Route::post('/claim',     [HotelController::class, 'claim']);

        Route::get('/pending-employees',            [HotelController::class, 'pendingEmployees']);
        Route::patch('/employees/{userId}/approve', [HotelController::class, 'approveEmployee']);
        Route::patch('/employees/{userId}/reject',  [HotelController::class, 'rejectEmployee']);
        Route::patch('/promote/{userId}',           [HotelController::class, 'promote']);
        Route::patch('/demote/{userId}',            [HotelController::class, 'demote']);
    });
});
```

---

## 10. Frontend — Tipos TypeScript

### 10.1 `hotel.types.ts` — extensão

```typescript
export interface Hotel {
  uuid: string
  name: string
  slug: string
  segment: string | null          // NOVO
  description: string | null
  logo_url: string | null
  cover_url: string | null
  city: string | null
  state: string | null
  country: string | null
  website: string | null
  phone: string | null
  contact_email: string | null
  followers_count: number         // NOVO
  employees_count: number         // NOVO
  jobs_count: number              // NOVO
  is_following: boolean           // NOVO
  has_admin: boolean              // NOVO
  created_at: string
}

export interface Employee {
  uuid: string
  name: string
  avatar_url: string | null
  title: string | null
  started_at: string | null
  verified: boolean
  hotel_user: {
    role: 'admin' | 'employee'
    status: 'pending' | 'approved' | 'rejected'
    created_at: string
  }
}
```

### 10.2 `user.types.ts` — extensão de Experience

```typescript
export interface Experience {
  id: number
  uuid: string
  title: string
  company: string
  hotel_id: number | null         // NOVO
  hotel?: Hotel | null            // NOVO (opcional, eager loaded)
  verified: boolean               // NOVO
  started_at: string
  ended_at: string | null
  description: string | null
}
```

---

## 11. Frontend — Serviços

### 11.1 `hotel.service.ts` — novos métodos

```typescript
/** POST /hotels/:slug/follow */
async followHotel(slug: string): Promise<ApiResponse<null>> {
  const res = await api.post<ApiResponse<null>>(`/hotels/${slug}/follow`)
  return res.data
},

/** DELETE /hotels/:slug/follow */
async unfollowHotel(slug: string): Promise<ApiResponse<null>> {
  const res = await api.delete<ApiResponse<null>>(`/hotels/${slug}/follow`)
  return res.data
},

/** GET /hotels/:slug/employees */
async getEmployees(slug: string): Promise<ApiResponse<Employee[]>> {
  const res = await api.get<ApiResponse<Employee[]>>(`/hotels/${slug}/employees`)
  return res.data
},

/** GET /hotels/:slug/pending-employees */
async getPendingEmployees(slug: string): Promise<ApiResponse<Employee[]>> {
  const res = await api.get<ApiResponse<Employee[]>>(`/hotels/${slug}/pending-employees`)
  return res.data
},

/** PATCH /hotels/:slug/employees/:userId/approve */
async approveEmployee(slug: string, userId: number): Promise<ApiResponse<null>> {
  const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/employees/${userId}/approve`)
  return res.data
},

/** PATCH /hotels/:slug/employees/:userId/reject */
async rejectEmployee(slug: string, userId: number): Promise<ApiResponse<null>> {
  const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/employees/${userId}/reject`)
  return res.data
},

/** POST /hotels/:slug/claim */
async claimHotel(slug: string, message?: string): Promise<ApiResponse<HotelClaim>> {
  const res = await api.post<ApiResponse<HotelClaim>>(`/hotels/${slug}/claim`, { message })
  return res.data
},
```

---

## 12. Frontend — Hooks

### 12.1 `useFollowHotel`

Arquivo: `src/hooks/useHotel.ts` (adicionado ao existente)

```typescript
export const hotelFollowKeys = {
  follow: (slug: string) => ['hotel', slug, 'follow'] as const,
}

export function useFollowHotel(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => hotelService.followHotel(slug),
    onSuccess: () => qc.invalidateQueries({ queryKey: hotelKeys.hotel(slug) }),
  })
}

export function useUnfollowHotel(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => hotelService.unfollowHotel(slug),
    onSuccess: () => qc.invalidateQueries({ queryKey: hotelKeys.hotel(slug) }),
  })
}
```

### 12.2 `useHotelEmployees`

```typescript
export const employeeKeys = {
  employees: (slug: string) => ['hotel', slug, 'employees'] as const,
  pending:   (slug: string) => ['hotel', slug, 'pending-employees'] as const,
}

export function useHotelEmployees(slug: string) {
  return useQuery({
    queryKey: employeeKeys.employees(slug),
    queryFn: () => hotelService.getEmployees(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

export function usePendingEmployees(slug: string) {
  return useQuery({
    queryKey: employeeKeys.pending(slug),
    queryFn: () => hotelService.getPendingEmployees(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

export function useApproveEmployee(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userId: number) => hotelService.approveEmployee(slug, userId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: employeeKeys.employees(slug) })
      qc.invalidateQueries({ queryKey: employeeKeys.pending(slug) })
    },
  })
}

export function useRejectEmployee(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userId: number) => hotelService.rejectEmployee(slug, userId),
    onSuccess: () => qc.invalidateQueries({ queryKey: employeeKeys.pending(slug) }),
  })
}
```

---

## 13. Frontend — Componentes Reutilizáveis

### 13.1 Estrutura de arquivos

```
src/components/
  common/
    UserLink.tsx          ← nome + avatar clicável → /users/:uuid
    CompanyLink.tsx       ← nome clicável → /hotels/:slug
    ProfileAvatar.tsx     ← avatar clicável → /users/:uuid
    CompanyAvatar.tsx     ← logo clicável → /hotels/:slug
    HoverCard.tsx         ← wrapper de hover card desktop
    ProfileCard.tsx       ← conteúdo do card de pessoa
    CompanyCard.tsx       ← conteúdo do card de hotel
  hotel/
    EmployeeCard.tsx      ← card de colaborador verificado
```

### 13.2 `UserLink`

```tsx
interface UserLinkProps {
  uuid: string
  name: string
  className?: string
  children?: React.ReactNode
}

export function UserLink({ uuid, name, className, children }: UserLinkProps) {
  return (
    <Link
      to={`/users/${uuid}`}
      className={cn('font-medium text-gray-900 hover:underline', className)}
    >
      {children ?? name}
    </Link>
  )
}
```

### 13.3 `CompanyLink`

```tsx
interface CompanyLinkProps {
  slug: string
  name: string
  className?: string
  children?: React.ReactNode
}

export function CompanyLink({ slug, name, className, children }: CompanyLinkProps) {
  return (
    <Link
      to={`/hotels/${slug}`}
      className={cn('font-medium text-gray-900 hover:underline', className)}
    >
      {children ?? name}
    </Link>
  )
}
```

### 13.4 `ProfileAvatar`

```tsx
interface ProfileAvatarProps {
  uuid: string
  name: string
  src?: string | null
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

export function ProfileAvatar({ uuid, name, src, size = 'sm' }: ProfileAvatarProps) {
  return (
    <Link to={`/users/${uuid}`} aria-label={`Perfil de ${name}`}>
      <Avatar src={src} name={name} size={size} />
    </Link>
  )
}
```

### 13.5 `CompanyAvatar`

```tsx
interface CompanyAvatarProps {
  slug: string
  name: string
  logoUrl?: string | null
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

export function CompanyAvatar({ slug, name, logoUrl, size = 'sm' }: CompanyAvatarProps) {
  return (
    <Link to={`/hotels/${slug}`} aria-label={`Perfil de ${name}`}>
      <Avatar src={logoUrl} name={name} size={size} />
    </Link>
  )
}
```

### 13.6 `EmployeeCard`

```tsx
interface EmployeeCardProps {
  employee: Employee
}

export function EmployeeCard({ employee }: EmployeeCardProps) {
  const duration = employee.started_at
    ? formatDuration(new Date(employee.started_at), new Date())
    : null

  return (
    <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-white p-4">
      <ProfileAvatar uuid={employee.uuid} name={employee.name} src={employee.avatar_url} size="md" />
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <UserLink uuid={employee.uuid} name={employee.name} className="text-sm font-semibold" />
          {employee.verified && (
            <span className="flex items-center gap-1 rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600">
              <CheckCircle className="h-3 w-3" /> Verificado
            </span>
          )}
        </div>
        {employee.title && (
          <p className="mt-0.5 text-xs text-gray-500">{employee.title}</p>
        )}
        {duration && (
          <p className="mt-0.5 text-xs text-gray-400">{duration}</p>
        )}
      </div>
    </div>
  )
}
```

### 13.7 `HoverCard`

Exibido apenas em viewports >= 1024px (Requisito 11.4). Utiliza estado de hover com delay de 300ms para evitar flashes.

```tsx
interface HoverCardProps {
  trigger: React.ReactNode
  content: React.ReactNode
}

export function HoverCard({ trigger, content }: HoverCardProps) {
  const [open, setOpen] = useState(false)
  const timerRef = useRef<ReturnType<typeof setTimeout>>()

  // Não renderiza em telas < 1024px
  const isDesktop = useMediaQuery('(min-width: 1024px)')
  if (!isDesktop) return <>{trigger}</>

  return (
    <div
      className="relative inline-block"
      onMouseEnter={() => { timerRef.current = setTimeout(() => setOpen(true), 300) }}
      onMouseLeave={() => { clearTimeout(timerRef.current); setOpen(false) }}
    >
      {trigger}
      {open && (
        <div className="absolute left-0 top-full z-50 mt-1 w-72 animate-scale-in rounded-2xl border border-gray-100 bg-white shadow-panel">
          {content}
        </div>
      )}
    </div>
  )
}
```

### 13.8 `ProfileCard`

```tsx
interface ProfileCardProps {
  uuid: string
  name: string
  avatarUrl?: string | null
  currentTitle?: string | null
  city?: string | null
  connectionsCount?: number
}
// Renderiza foto, nome, cargo, cidade, contagem de conexões e botão "Conectar"
```

### 13.9 `CompanyCard`

```tsx
interface CompanyCardProps {
  slug: string
  name: string
  logoUrl?: string | null
  segment?: string | null
  city?: string | null
  followersCount?: number
  jobsCount?: number
  isFollowing?: boolean
}
// Renderiza logo, nome, segmento, localização, seguidores, vagas ativas e botão "Seguir"
```

---

## 14. Frontend — Alterações em Páginas e Layout

### 14.1 `AppLayout` — Topbar

Remover o item `{ label: 'Criar hotel', to: '/hotels/new' }` do array de itens do dropdown do usuário (Requisito 2.2).

Antes:
```tsx
{ label: 'Criar hotel', to: '/hotels/new' },
```
Depois: item removido. O array `NAV_MAIN` não é alterado.

### 14.2 `AppLayout` — Sidebar

Adicionar seção "Empresas / Hotéis" após o separador `Mais` na navegação:

```tsx
{!collapsed && (
  <>
    <div className="my-3 h-px bg-gray-100" />
    <p className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">
      Empresas / Hotéis
    </p>
    <SideNavItem to="/hotels" label="Meus Hotéis" Icon={Building2} />
    <SideNavItem to="/hotels/new" label="Criar Hotel" Icon={Plus} />
  </>
)}
```

### 14.3 `ProfilePage` — remoção do botão "Criar Hotel"

Localizar e remover o botão/link que redireciona para `/hotels/new` dentro de `ProfilePage` (Requisito 2.1).

### 14.4 `HotelProfilePage` — Refatoração com Abas

A página passa a exibir abas: **Sobre**, **Publicações**, **Colaboradores**, **Vagas**.

```tsx
type TabId = 'about' | 'posts' | 'employees' | 'jobs'

const TABS: { id: TabId; label: string }[] = [
  { id: 'about',     label: 'Sobre' },
  { id: 'posts',     label: 'Publicações' },
  { id: 'employees', label: 'Colaboradores' },
  { id: 'jobs',      label: 'Vagas' },
]
```

**Action bar** (abaixo do header):
- Botão "Seguir" / "Deixar de Seguir" condicional baseado em `hotel.is_following`
- Botão "Ver Vagas" → navega para aba Vagas ou `/hotels/:slug/jobs`
- Botão "Compartilhar" → copia URL para clipboard
- Botão "Editar Perfil do Hotel" → visível apenas para admins
- Botão "Reivindicar este Hotel" → visível apenas se `!hotel.has_admin`

**Aba Colaboradores** usa `EmployeeCard` para cada employee retornado por `useHotelEmployees`. Admin vê adicionalmente a lista de pendentes com botões Aprovar/Rejeitar.

### 14.5 Roteamento Frontend — alias `/company/:slug`

No arquivo `src/routes/index.tsx`, adicionar:

```tsx
// Alias /company/:slug → /hotels/:slug
<Route path="/company/:slug" element={<Navigate to={(params) => `/hotels/${params.slug}`} replace />} />
```

Ou, alternativamente, registrar a mesma página em duas rotas:

```tsx
<Route path="/hotels/:slug"  element={<HotelProfilePage />} />
<Route path="/company/:slug" element={<HotelProfilePage />} />
```

A segunda abordagem é preferível pois não recarrega a página.

### 14.6 Nova página `/hotels` — "Meus Hotéis"

Arquivo: `src/pages/hotel/MyHotelsPage.tsx`

Lista os hotéis onde o usuário é admin ou employee aprovado (via `findByMember`). Inclui link para criação de novo hotel.

---

## 15. Tratamento de Erros e Edge Cases

### 15.1 Backend

- **`ConflictException`** (HTTP 409): lançada por `FollowerService::follow` e `ClaimService::claim` quando há duplicidade. Mapear para resposta JSON via `Handler.php`.
- **`UnprocessableEntityException`** (HTTP 422): lançada por `HotelService::demoteToEmployee` quando a remoção resultaria em hotel sem admin.
- **Soft deletes**: o modelo `Hotel` usa `SoftDeletes`. Consultas ao hotel em rotas públicas devem retornar 404 se o registro estiver deletado (comportamento padrão do Eloquent).
- **Contadores de seguidores**: usar `DB::transaction` no `FollowerService` para garantir atomicidade entre inserção e `increment`.

### 15.2 Frontend

- **Otimistic update**: ao clicar em "Seguir", atualizar `is_following` e `followers_count` localmente antes da resposta da API; reverter em caso de erro.
- **Cache do HoverCard**: usar `staleTime: 5 * 60 * 1000` (5 minutos) nas queries dos cards de prévia para evitar chamadas repetidas (Requisito 11.5).
- **Erro 404**: `HotelProfilePage` já trata `isError` exibindo mensagem; estender para distinguir 404 (hotel não encontrado) de outros erros de rede.

---

## 16. Modelo de Dados — Diagrama Resumido

```
users
 └─── hotel_users ──────────────── hotels
      (user_id, hotel_id,           (followers_count, segment)
       role, status)                     │
                                         ├── hotel_followers (user_id, hotel_id)
experiences                              │
 (user_id, hotel_id, verified)           └── hotel_claims (user_id, status)
```

**Cardinalidades principais:**
- Um `Hotel` tem muitos `HotelUser` (0..N admins, 0..N employees)
- Um `HotelUser` pertence a 1 `Hotel` e 1 `User`
- Um `Hotel` tem muitos `HotelFollower` (0..N)
- Uma `Experience` pertence a 0 ou 1 `Hotel` (nullable FK)
- Um `Hotel` tem muitos `HotelClaim` (0..N)

---

## Correctness Properties

*Uma propriedade é uma característica ou comportamento que deve ser verdadeiro em todas as execuções válidas do sistema — essencialmente, uma afirmação formal sobre o que o sistema deve fazer. As propriedades servem como ponte entre especificações legíveis por humanos e garantias de corretude verificáveis por máquina.*

### Propriedade 1: Unicidade de slug gerado

*Para qualquer* nome de hotel válido, o slug gerado deve conter apenas caracteres URL-seguros (`a-z`, `0-9`, hifens) e deve ser único em relação a todos os slugs já existentes na tabela `hotels`.

**Validates: Requirements 3.1, 3.4**

---

### Propriedade 2: Invariante de admin após criação

*Para qualquer* hotel recém-criado por qualquer usuário autenticado, o hotel deve possuir exatamente um registro em `hotel_users` com `role = 'admin'`, `status = 'approved'` e `user_id` igual ao criador.

**Validates: Requirements 3.2, 3.3**

---

### Propriedade 3: Invariante de ao menos um admin após operações de role

*Para qualquer* sequência válida de operações de promoção e rebaixamento de admins, o hotel deve sempre possuir ao menos um registro ativo com `role = 'admin'` e `status = 'approved'`. A operação de rebaixamento deve ser rejeitada (HTTP 422) se resultaria em zero admins.

**Validates: Requirements 3.3, 9.7, 9.8**

---

### Propriedade 4: Round-trip de persistência dos campos do Hotel

*Para qualquer* conjunto de campos válidos de hotel (incluindo `segment`), criar o hotel via `HotelService` e em seguida recuperá-lo pelo slug deve retornar um objeto cujos campos são idênticos aos enviados na criação.

**Validates: Requirements 3.5**

---

### Propriedade 5: Contagens corretas no endpoint de perfil

*Para qualquer* hotel no banco de dados, o endpoint `GET /hotels/:slug` deve retornar `employees_count` igual ao número de registros em `hotel_users` com `role='employee'` e `status='approved'`, `jobs_count` igual ao número de vagas com `status='active'`, e `followers_count` igual ao número de registros em `hotel_followers`.

**Validates: Requirements 4.9, 8.2, 8.3**

---

### Propriedade 6: Filtro correto de colaboradores aprovados

*Para qualquer* hotel com N colaboradores aprovados e M pendentes ou rejeitados, o endpoint `GET /hotels/:slug/employees` deve retornar exatamente N registros, todos com `status = 'approved'` e `role = 'employee'`.

**Validates: Requirements 5.1**

---

### Propriedade 7: Cálculo de tempo na empresa não-negativo e monotônico

*Para qualquer* data de início de experiência `started_at` válida no passado, o cálculo de tempo na empresa (`formatDuration(started_at, now)`) deve retornar um valor >= 0 e maior para datas mais antigas do que para datas mais recentes.

**Validates: Requirements 5.4**

---

### Propriedade 8: Idempotência de criação de vínculo hotel_users

*Para qualquer* usuário que vincula N experiências distintas ao mesmo hotel, deve existir exatamente 1 registro em `hotel_users` para o par `(user_id, hotel_id)`, independentemente do número de vínculos realizados.

**Validates: Requirements 6.4**

---

### Propriedade 9: Round-trip de aprovação e rejeição de vínculo

*Para qualquer* solicitação de vínculo com `status = 'pending'`:
- Após aprovar: `hotel_users.status = 'approved'` **E** `experiences.verified = true`
- Após rejeitar: `hotel_users.status = 'rejected'`

*Para qualquer* estado final (`approved` ou `rejected`), o estado nunca deve ser `pending`.

**Validates: Requirements 6.6, 6.7**

---

### Propriedade 10: Round-trip de seguir e deixar de seguir

*Para qualquer* hotel com `followers_count = N` e qualquer usuário que ainda não segue esse hotel, após seguir e imediatamente deixar de seguir, `followers_count` deve retornar a `N` e não deve existir registro em `hotel_followers` para esse par `(user_id, hotel_id)`.

**Validates: Requirements 8.2, 8.3**

---

### Propriedade 11: Consistência de `is_following`

*Para qualquer* par `(usuário, hotel)`, o campo `is_following` retornado pelo endpoint de perfil do hotel deve ser `true` se e somente se existe um registro correspondente em `hotel_followers`.

**Validates: Requirements 8.6**

---

### Propriedade 12: Idempotência de reivindicação pending

*Para qualquer* usuário que tente criar duas reivindicações para o mesmo hotel sem que a primeira tenha sido resolvida, a segunda requisição deve retornar HTTP 409 e deve existir exatamente 1 registro em `hotel_claims` com `status = 'pending'` para esse par `(user_id, hotel_id)`.

**Validates: Requirements 7.6**

---

### Propriedade 13: Enforcement de permissões administrativas

*Para qualquer* requisição de edição de hotel, gerenciamento de vagas ou aprovação de colaboradores realizada por um usuário que não possui `role = 'admin'` com `status = 'approved'` para o hotel alvo, o sistema deve retornar HTTP 403, sem persistir nenhuma alteração.

**Validates: Requirements 9.1, 9.3**

---

### Propriedade 14: Links clicáveis com href correto

*Para qualquer* usuário com `uuid` U, o componente `UserLink` renderizado deve conter um elemento `<a>` cujo atributo `href` aponta para `/users/U`. *Para qualquer* hotel com `slug` S, o componente `CompanyLink` renderizado deve conter um elemento `<a>` cujo atributo `href` aponta para `/hotels/S`.

**Validates: Requirements 10.1, 10.2, 10.3, 10.4**

---
