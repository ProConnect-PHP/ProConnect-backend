<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\ListProfessionalAgendaAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\ListProfessionalAgendaRequest;
use App\Http\Resources\Booking\ProfessionalAgendaEventResource;
use App\Models\User\ProfessionalProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalAgendaController extends Controller
{
    public function index(
        ListProfessionalAgendaRequest $request,
        ListProfessionalAgendaAction $action
    ): JsonResponse {
        $user = auth('user_jwt')->user();

        /** @var ProfessionalProfile|null $professionalProfile */
        $professionalProfile = $user->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para ver tu agenda.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        $view = $request->validated('view') ?? 'week';
        [$from, $to] = $this->resolveRange(
            view: $view,
            date: $request->validated('date'),
            from: $request->validated('from'),
            to: $request->validated('to'),
        );

        if ($from->diffInDays($to) > 62) {
            throw new ApiException(
                error: 'AgendaRangeTooLarge',
                message: 'El rango maximo permitido para consultar agenda es de 62 dias.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $action(
            professionalProfile: $professionalProfile,
            from: $from,
            to: $to,
            status: $request->validated('status'),
            serviceId: $request->integer('service_id') ?: null,
        );

        return response()->json([
            'timezone' => config('app.timezone'),
            'view' => $view,
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
            'events' => ProfessionalAgendaEventResource::collection($result['bookings']),
            'summary' => $result['range_summary'],
            'range_summary' => $result['range_summary'],
            'global_summary' => $result['global_summary'],
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(
        string $view,
        ?string $date,
        ?string $from,
        ?string $to,
    ): array {
        $timezone = config('app.timezone');

        if ($date !== null) {
            $referenceDate = CarbonImmutable::parse($date, $timezone);

            return $view === 'month'
                ? [$referenceDate->startOfMonth()->startOfDay(), $referenceDate->endOfMonth()->endOfDay()]
                : [$referenceDate->startOfWeek()->startOfDay(), $referenceDate->endOfWeek()->endOfDay()];
        }

        return [
            CarbonImmutable::parse($from, $timezone)->startOfDay(),
            CarbonImmutable::parse($to, $timezone)->endOfDay(),
        ];
    }
}
