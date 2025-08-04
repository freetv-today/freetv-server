
export function ButtonShowTitleNav(props) {
  return (
    <div class="btn-group mb-1" style="width: 98%;">
        <button class="btn btn-sm btn-outline-dark" style="width: calc(100% - 3rem);" title="Watch {props.title}" aria-label="Watch: {props.title}">
        {props.title}
        </button>
        <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="width: 3rem;">
        <span class="visually-hidden">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu p-2">
        <li><a class="dropdown-item moreoptions" href="javascript:void(0)" title="About this show">About this show</a></li>
        <div class="moduleBtns">
            <li>
            <a class="dropdown-item moreoptions text-danger" href="javascript:void(0)" title="Report a problem">Report a problem</a>
            </li>
            <li>
            <a class="dropdown-item moreoptions text-secondary" href="javascript:void(0)" title="Share this video">Share this video</a>
            </li>
            <li>
            <a class="dropdown-item moreoptions text-secondary" href="javascript:void(0)" title="Add to favorites">Add to favorites</a>
            </li>
        </div>
        </ul>
    </div>
  );
}