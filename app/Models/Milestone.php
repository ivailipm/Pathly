use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'title', 'description', 'position'])]
class Milestone extends Model
{
/** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    public function subject(): BelongsTo
    {
    return $this->belongsTo(Subject::class);
    }

    public function tasks(): HasMany
    {
    return $this->hasMany(Task::class)->orderBy('position');
    }
    }