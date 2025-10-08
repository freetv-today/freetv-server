import { Link } from '@components/Navigation/Link';
import freetvLogo from '/assets/freetv.png';
import { createPath } from '@/utils/env';

export function ImageLargeLogo() {
  return (
    <div className="text-center mt-4">
		<h1 className="display-4 bruno-ace noselect">Free TV</h1>
		<p className="pb-4">
			<Link href={createPath('/')} className="m-0 p-0">
				<img src={freetvLogo} width="175" title="Watch Free TV!" alt="Free TV logo" />
			</Link>
		</p>
	</div>
  );
}