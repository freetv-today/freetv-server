import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';
import { freeTvLogo } from '@/adminAssets';

export function ImageLargeLogo() {
  return (
    <div className="text-center mt-4">
		<h1 className="display-4 bruno-ace noselect">Free TV</h1>
		<p className="pb-4">
			<Link href={createPath('/dashboard')} className="m-0 p-0">
				<img src={freeTvLogo} width="175" title="Watch Free TV!" alt="Free TV logo" />
			</Link>
		</p>
	</div>
  );
}
